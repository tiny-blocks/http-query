#!/usr/bin/env python3
"""PHP ordering conformance hook for tiny-blocks PHP libraries.

Self-contained PostToolUse hook on Edit|Write|MultiEdit. Verifies the deterministic
ordering conventions for PHP declarations:

- Parameter ordering: declaration parameters (constructors, factories, methods,
  property promotion) ordered by identifier length ascending, alphabetical
  tie-breaker, semantic pairs preserved.
- Parameter ordering: named arguments at call sites, same comparator.
- Member ordering: constants, enum cases, constructor, static methods, instance
  methods, in that group order, each group length-ascending with alphabetical
  tie-breaker.

The analysis is pure (FileUnit in, Violation out) and runs in three passes over
well-formed PHP: a lexical pass blanks every comment, string, and heredoc/nowdoc
body (LITERALS), a structural pass maps every bracket to its pair (bracket_spans);
extraction assigns tokens of interest to their containers by flat walks. Control
flow uses guard clauses only and nesting never exceeds two levels. Reports
violations to stderr and exits 2 to prompt Claude, exits 0 silently if no violations
or the file is out of scope.
"""

import json
import re
import sys
from dataclasses import dataclass
from enum import Enum
from functools import cached_property
from pathlib import Path
from typing import Final

# --- Configuration ----------------------------------------------------------

# In-scope files: PHP sources under src/ or tests/.
SCOPE_PATTERN: Final = re.compile(r"(^|/)(src|tests)/.+\.php$")

# Semantic pairs (exhaustive). Natural order wins between
# the two members when both appear in the same parameter list.
SEMANTIC_PAIRS: Final = (
    ("start", "end"),
    ("from", "to"),
    ("startAt", "endAt"),
    ("createdAt", "updatedAt"),
    ("before", "after"),
    ("min", "max"),
)

# Each member maps to (first, second, position). Both members keep their natural
# order only when both are present, sorting as a unit at the lead member's key.
PAIR_MEMBER: Final = {
    member: (first, second, position)
    for first, second in SEMANTIC_PAIRS
    for position, member in enumerate((first, second))
}

MODIFIERS: Final = ("abstract", "final", "private", "protected", "public", "static")

# The lexical grammar: every PHP construct that must not be scanned as code.
# Alternatives are ordered, the heredoc label closes via backreference.
LITERALS: Final = re.compile(
    r"""
      /\*.*?\*/                                  # block comment
    | //[^\n]*                                   # line comment
    | \#(?!\[)[^\n]*                             # hash comment, never a #[ attribute
    | <<<[ \t]*(?P<quote>['"]?)(?P<label>\w+)(?P=quote)[^\n]*\n
      .*?\n[ \t]*(?P=label)\b                    # heredoc and nowdoc body
    | '(?:\\.|[^'\\])*'                          # single-quoted string
    | "(?:\\.|[^"\\])*"                          # double-quoted string
    """,
    re.DOTALL | re.MULTILINE | re.VERBOSE,
)

TYPE_DECLARATION: Final = re.compile(
    r"^[ \t]*(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)",
    re.MULTILINE,
)
FUNCTION_DECLARATION: Final = re.compile(r"\bfunction\s+&?(\w+)\s*\(")
METHOD_LINE: Final = re.compile(
    rf"^\s*((?:(?:{'|'.join(MODIFIERS)})\s+)+)function\s+&?(\w+)\s*\("
)
CONST_LINE: Final = re.compile(
    r"^\s*(?:(?:final|private|protected|public)\s+)*const\s+(?:[?\w|&()\s]+\s)?(\w+)\s*="
)
CASE_LINE: Final = re.compile(r"^\s*case\s+(\w+)\s*[=;]")

PARAMETER: Final = re.compile(r"\$(\w+)")
NAMED_ARGUMENT: Final = re.compile(r"[(,]\s*(\w+):(?!:)")

MAX_ERRORS_REPORTED = 30


# --- Types --------------------------------------------------------------------


@dataclass(frozen=True)
class Violation:
    """One style violation at a source position."""

    line: int
    path: str
    message: str

    def __str__(self) -> str:
        return f"{self.path}:{self.line}: {self.message}"


@dataclass(frozen=True)
class Source:
    """PHP source with literals blanked out at their original positions."""

    clean: str

    @staticmethod
    def blanked(literal: re.Match[str]) -> str:
        """The matched literal as spaces, newlines preserved."""
        return re.sub(r"[^\n]", " ", literal.group(0))

    @classmethod
    def from_php(cls, text: str) -> "Source":
        """A source with every comment, string, and heredoc blanked out."""
        return cls(clean=LITERALS.sub(cls.blanked, text))

    def line_of(self, index: int) -> int:
        """The 1-based line number of a character index."""
        return self.clean.count("\n", 0, index) + 1


@dataclass(frozen=True)
class FileUnit:
    """One file under analysis: its raw text and the blanked Source on demand."""

    path: str
    text: str

    @cached_property
    def source(self) -> Source:
        """The PHP source with literals blanked, computed once per file."""
        return Source.from_php(self.text)


@dataclass(frozen=True)
class Ordering:
    """Sole owner of the parameter-ordering logic for one variant."""

    pair_member: dict[str, tuple[str, str, int]]

    def sorted(self, names: list[str]) -> list[str]:
        """The names in the required order."""
        present = set(names)
        return sorted(names, key=lambda name: self.key(name, present))

    def key(self, name: str, present: set[str]) -> tuple[int, str, int]:
        """Length, then alphabet, with both members of a present pair at the lead key."""
        entry = self.pair_member.get(name)
        if entry is not None:
            first, second, position = entry
            partner = second if position == 0 else first
            if partner in present:
                return (len(first), first, position)
        return (len(name), name, 0)

    def violated_by(self, names: list[str]) -> bool:
        """Whether the names break the required order."""
        return names != self.sorted(names)


PARAMETER_ORDERING: Final = Ordering(pair_member=PAIR_MEMBER)
MEMBER_ORDERING: Final = Ordering(pair_member={})


class MemberKind(Enum):
    """Closed set of class-member groups in required declaration order."""

    CONSTANT = (0, "const")
    CASE = (1, "case")
    CONSTRUCTOR = (2, "constructor")
    STATIC_METHOD = (3, "static method")
    INSTANCE_METHOD = (4, "instance method")

    @property
    def rank(self) -> int:
        return self.value[0]

    @property
    def label(self) -> str:
        return self.value[1]

    def precedes(self, other: "MemberKind") -> bool:
        """Whether this group must appear before the other group."""
        return self.rank < other.rank


@dataclass(frozen=True)
class Member:
    """One classified class member at its declaration line."""

    kind: MemberKind
    line: int
    name: str


@dataclass(frozen=True)
class TypeMembers:
    """The members of one class-like declaration in source order."""

    name: str
    members: list[Member]


@dataclass(frozen=True)
class ParameterList:
    """One declaration's parameter identifiers in source order."""

    line: int
    names: list[str]
    owner: str

    def required(self) -> list[str]:
        return PARAMETER_ORDERING.sorted(self.names)

    def out_of_order(self) -> bool:
        return len(self.names) >= 2 and PARAMETER_ORDERING.violated_by(self.names)


@dataclass
class ArgumentGroup:
    """The named arguments collected inside one parenthesized list."""

    names: list[tuple[str, int]]

    def required(self) -> list[str]:
        return PARAMETER_ORDERING.sorted(self.identifiers())

    def opened_at(self) -> int:
        return self.names[0][1]

    def identifiers(self) -> list[str]:
        return [name for name, _ in self.names]

    def out_of_order(self) -> bool:
        identifiers = self.identifiers()
        return len(identifiers) >= 2 and PARAMETER_ORDERING.violated_by(identifiers)


# --- Structure ----------------------------------------------------------------


def bracket_spans(text: str) -> dict[int, int]:
    """Every opening bracket position mapped to its closing position."""
    spans: dict[int, int] = {}
    stack: list[int] = []

    for position, character in enumerate(text):
        if character in "([{":
            stack.append(position)

        if character in ")]}" and stack:
            spans[stack.pop()] = position

    return spans


def top_level_pieces(text: str) -> list[str]:
    """The comma-separated pieces of a list, ignoring nested separators."""
    pieces, depth, current = [], 0, []
    for character in text:
        if character in "([{":
            depth += 1

        if character in ")]}":
            depth -= 1

        if character == "," and depth == 0:
            pieces.append("".join(current))
            current = []
            continue
        current.append(character)

    tail = "".join(current).strip()

    if tail:
        pieces.append(tail)

    return pieces


# --- Extraction ---------------------------------------------------------------


def declared_signatures(source: Source) -> list[ParameterList]:
    """Every function or method declaration with its parameter identifiers."""
    signatures = []
    spans = bracket_spans(source.clean)

    for match in FUNCTION_DECLARATION.finditer(source.clean):
        open_paren = source.clean.index("(", match.end() - 1)
        inner = source.clean[open_paren + 1: spans.get(open_paren, len(source.clean))]
        names = [
            found[-1]
            for piece in top_level_pieces(inner)
            if (found := PARAMETER.findall(piece))
        ]

        signatures.append(ParameterList(
            line=source.line_of(match.start()),
            names=names,
            owner=match.group(1),
        ))
    return signatures


def named_argument_groups(source: Source) -> list[ArgumentGroup]:
    """Every parenthesized list with the named arguments it carries."""
    arguments = {match.start(1): match.group(1) for match in NAMED_ARGUMENT.finditer(source.clean)}
    groups: list[ArgumentGroup] = []
    stack: list[ArgumentGroup] = []

    for position, character in enumerate(source.clean):
        if character == "(":
            stack.append(ArgumentGroup(names=[]))

        if character == ")" and stack:
            groups.append(stack.pop())

        if position in arguments and stack:
            stack[-1].names.append((arguments[position], source.line_of(position)))
    return groups


def class_members(source: Source) -> list[TypeMembers]:
    """Every class-like declaration with its members in source order."""
    declarations = []
    spans = bracket_spans(source.clean)

    for type_match in TYPE_DECLARATION.finditer(source.clean):
        body_open = source.clean.find("{", type_match.end())

        if body_open == -1:
            continue

        body = source.clean[body_open + 1: spans.get(body_open, len(source.clean))]
        declarations.append(TypeMembers(
            name=type_match.group(2),
            members=declared_members(body=body, at_line=source.line_of(body_open)),
        ))
    return declarations


def declared_members(body: str, at_line: int) -> list[Member]:
    """The members declared at the top level of one type body."""
    members, depth = [], 0
    for offset, line in enumerate(body.split("\n")):
        if depth == 0 and (member := classified(at=at_line + offset, line=line)):
            members.append(member)
        depth += line.count("{") - line.count("}")
    return members


def classified(at: int, line: str) -> Member | None:
    """The member a line declares, when it declares one."""
    method = METHOD_LINE.match(line)
    if method:
        name = method.group(2)
        if name == "__construct":
            return Member(kind=MemberKind.CONSTRUCTOR, line=at, name=name)

        if "static" in method.group(1).split():
            return Member(kind=MemberKind.STATIC_METHOD, line=at, name=name)
        return Member(kind=MemberKind.INSTANCE_METHOD, line=at, name=name)

    constant = CONST_LINE.match(line)

    if constant:
        return Member(kind=MemberKind.CONSTANT, line=at, name=constant.group(1))
    case = CASE_LINE.match(line)

    if case:
        return Member(kind=MemberKind.CASE, line=at, name=case.group(1))
    return None


# --- Checks -------------------------------------------------------------------


def parameter_violations(unit: FileUnit) -> tuple[Violation, ...]:
    """Parameter ordering on every function and method declaration."""
    return tuple(
        Violation(
            line=signature.line,
            path=unit.path,
            message=(
                f"parameter order in `{signature.owner}()` is "
                f"({', '.join(signature.names)}), "
                f"required ({', '.join(signature.required())})"
            ),
        )

        for signature in declared_signatures(unit.source)
        if signature.out_of_order()
    )


def named_argument_violations(unit: FileUnit) -> tuple[Violation, ...]:
    """Parameter ordering on named arguments at call sites."""
    return tuple(
        Violation(
            line=group.opened_at(),
            path=unit.path,
            message=(
                f"named-argument order is ({', '.join(group.identifiers())}), "
                f"required ({', '.join(group.required())})"
            ),
        )

        for group in named_argument_groups(unit.source)
        if group.out_of_order()
    )


def member_violations(unit: FileUnit) -> tuple[Violation, ...]:
    """Member ordering on group sequence and order within each group."""
    violations: list[Violation] = []
    for declared in class_members(unit.source):
        violations.extend(group_sequence_violations(path=unit.path, declared=declared))
        violations.extend(within_group_violations(path=unit.path, declared=declared))
    return tuple(violations)


def group_sequence_violations(path: str, declared: TypeMembers) -> tuple[Violation, ...]:
    """Members declared after a group that must come later."""
    violations = []
    latest = MemberKind.CONSTANT
    for member in declared.members:
        if member.kind.precedes(latest):
            violations.append(Violation(
                line=member.line,
                path=path,
                message=(
                    f"`{member.name}` ({member.kind.label}) declared after a later "
                    f"group in `{declared.name}`, required group order const, case, "
                    f"constructor, static methods, instance methods"
                ),
            ))

        if latest.precedes(member.kind):
            latest = member.kind
    return tuple(violations)


def within_group_violations(path: str, declared: TypeMembers) -> tuple[Violation, ...]:
    """Groups whose members break the length-then-alphabet order."""
    violations = []
    for kind in MemberKind:
        if kind is MemberKind.CONSTRUCTOR:
            continue
        grouped = [member for member in declared.members if member.kind is kind]
        names = [member.name for member in grouped]

        if not names or not MEMBER_ORDERING.violated_by(names):
            continue

        violations.append(Violation(
            line=grouped[0].line,
            path=path,
            message=(
                f"{kind.label} order in `{declared.name}` is ({', '.join(names)}), "
                f"required ({', '.join(MEMBER_ORDERING.sorted(names))})"
            ),
        ))
    return tuple(violations)


def ordering_violations(unit: FileUnit) -> tuple[Violation, ...]:
    """Every ordering violation in one PHP file: members, named arguments, parameters."""
    return (
        *member_violations(unit),
        *named_argument_violations(unit),
        *parameter_violations(unit),
    )


# --- Shell --------------------------------------------------------------------


def requested_paths() -> list[Path]:
    """The paths to verify, from argv or from the hook's stdin payload."""
    if len(sys.argv) > 1:
        return [Path(argument) for argument in sys.argv[1:]]
    try:
        payload = json.load(sys.stdin)
    except ValueError:
        return []

    file_path = (payload.get("tool_input") or {}).get("file_path")

    if isinstance(file_path, str):
        return [Path(file_path)]
    return []


def in_scope(path: Path) -> bool:
    """Whether the path is a PHP source under src/ or tests/."""
    return bool(SCOPE_PATTERN.search(path.as_posix())) and path.is_file()


def file_violations(path: Path) -> tuple[Violation, ...]:
    """The ordering violations for one file."""
    unit = FileUnit(
        path=path.as_posix(),
        text=path.read_text(errors="replace", encoding="utf-8"),
    )
    return ordering_violations(unit)


def main() -> int:
    violations = [
        violation
        for path in requested_paths()
        if in_scope(path)
        for violation in file_violations(path)
    ]

    if not violations:
        return 0

    for violation in violations[:MAX_ERRORS_REPORTED]:
        print(violation, file=sys.stderr)
    overflow = len(violations) - MAX_ERRORS_REPORTED

    if overflow > 0:
        print(f"... and {overflow} more violations", file=sys.stderr)
    return 2


if __name__ == "__main__":
    sys.exit(main())
