"""Conservative text cleaning utilities."""

import re


def clean_text(text: str) -> str:
    """Normalize spacing while preserving punctuation, IDs, names, and grades."""
    normalized = text.replace("\r\n", "\n").replace("\r", "\n")
    lines = [re.sub(r"[ \t\f\v]+", " ", line).strip() for line in normalized.split("\n")]

    cleaned_lines: list[str] = []
    blank_seen = False
    for line in lines:
        if not line:
            if not blank_seen:
                cleaned_lines.append("")
            blank_seen = True
            continue
        cleaned_lines.append(line)
        blank_seen = False

    return "\n".join(cleaned_lines).strip()

