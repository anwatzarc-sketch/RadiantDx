#!/usr/bin/env python3
"""
One-off converter: RadiantLab HL7 master-data workbook -> committed JSON.

    python database/seeders/data/tools/convert_hl7_workbook.py "path/to/workbook.xlsx"

The seeders read only the JSON this writes (database/seeders/data/hl7/), never
the workbook, so production seeding has no spreadsheet dependency and every
change to the master data arrives as a reviewable diff.

Standard library only. The workbook has no formulas, so reading the cached
cell values straight out of the OOXML is exact, and nothing has to be
installed to re-run this.

What the converter decides, so the seeders do not have to:

  * Empty cells, "NaN" and "" become null. LOINC/SNOMED placeholders such as
    "(select per kit)" become null too: a placeholder is not a code, and
    storing one would put a fake identifier into an HL7 message later.
  * Numbers become numbers, rounded to 6 places (the decimal(18,6) the
    catalogue columns use). That also removes float artefacts such as
    8.300000000000001 that the spreadsheet carries.
  * Parameter codes the application's code rule rejects are renamed here,
    once, and consistently across the catalogue and the ranges. The original
    is kept as source_code. LOINC, not the local code, is the
    interoperability key.

It refuses to write anything if the reference ranges fail validation.
"""

from __future__ import annotations

import json
import math
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from collections import defaultdict
from pathlib import Path

NS = {
    "m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
}

HEADER_ROW = 4
FIRST_DATA_ROW = 5

OUT_DIR = Path(__file__).resolve().parents[1] / "hl7"

# The application accepts [A-Za-z0-9._-] in codes; '%' is not allowed.
CODE_RENAMES = {
    "NEUT%": "NEUT-PCT",
    "LYMPH%": "LYMPH-PCT",
    "MONO%": "MONO-PCT",
    "EOS%": "EOS-PCT",
    "BASO%": "BASO-PCT",
}

PLACEHOLDER_CODES = {
    "(select per kit)",
    "(verify)",
    "(local)",
    "(snomed organism – verify)",
    "(snomed organism - verify)",
}

AGE_UNITS_IN_DAYS = {"d": 1.0, "mo": 30.4375, "a": 365.25}
SEXES = {"any", "M", "F"}
MULTI_PARAMETER_MARKER = "(multi-parameter test)"


class ConversionError(Exception):
    pass


# --------------------------------------------------------------------------
# Workbook reading
# --------------------------------------------------------------------------


class Workbook:
    def __init__(self, path: Path):
        self.zip = zipfile.ZipFile(path)
        self.shared = self._shared_strings()
        self.sheets = self._sheet_targets()

    def _shared_strings(self) -> list[str]:
        if "xl/sharedStrings.xml" not in self.zip.namelist():
            return []
        root = ET.fromstring(self.zip.read("xl/sharedStrings.xml"))
        return [
            "".join(t.text or "" for t in si.iter("{%s}t" % NS["m"]))
            for si in root.findall("m:si", NS)
        ]

    def _sheet_targets(self) -> dict[str, str]:
        workbook = ET.fromstring(self.zip.read("xl/workbook.xml"))
        rels = ET.fromstring(self.zip.read("xl/_rels/workbook.xml.rels"))
        targets = {rel.get("Id"): rel.get("Target") for rel in rels}
        sheets = {}
        for sheet in workbook.find("m:sheets", NS):
            target = targets[sheet.get("{%s}id" % NS["r"])].lstrip("/")
            sheets[sheet.get("name")] = target if target.startswith("xl/") else "xl/" + target
        return sheets

    def rows(self, name: str) -> dict[int, list]:
        if name not in self.sheets:
            raise ConversionError(f"Sheet '{name}' not found. Sheets: {', '.join(self.sheets)}")
        root = ET.fromstring(self.zip.read(self.sheets[name]))
        rows: dict[int, list] = {}
        for row in root.iter("{%s}row" % NS["m"]):
            cells = {}
            for cell in row.findall("m:c", NS):
                cells[_column_index(cell.get("r"))] = self._value(cell)
            if cells:
                rows[int(row.get("r"))] = [cells.get(i) for i in range(max(cells) + 1)]
        return rows

    def _value(self, cell):
        kind = cell.get("t")
        value = cell.find("m:v", NS)
        if kind == "s":
            return self.shared[int(value.text)]
        if kind == "inlineStr":
            return "".join(t.text or "" for t in cell.iter("{%s}t" % NS["m"]))
        if kind == "b":
            return value is not None and value.text == "1"
        if value is None:
            return None
        text = value.text
        if kind in ("str", "e"):
            return text
        try:
            number = float(text)
        except (TypeError, ValueError):
            return text
        return int(number) if number.is_integer() else number

    def records(self, name: str) -> list[dict]:
        """Data rows keyed by header text. Blank rows are skipped."""
        rows = self.rows(name)
        if HEADER_ROW not in rows:
            raise ConversionError(f"Sheet '{name}' has no header on row {HEADER_ROW}.")
        headers = [str(h).strip() if h is not None else None for h in rows[HEADER_ROW]]
        out = []
        for number in sorted(r for r in rows if r >= FIRST_DATA_ROW):
            values = rows[number]
            record = {h: (values[i] if i < len(values) else None) for i, h in enumerate(headers) if h}
            if all(clean(v) is None for v in record.values()):
                continue
            record["__row"] = number
            out.append(record)
        return out


def _column_index(reference: str) -> int:
    letters = re.match(r"[A-Z]+", reference).group(0)
    index = 0
    for letter in letters:
        index = index * 26 + ord(letter) - 64
    return index - 1


# --------------------------------------------------------------------------
# Normalisation
# --------------------------------------------------------------------------


def clean(value):
    """Blank, NaN and "" to None; strings trimmed; numbers left alone."""
    if value is None:
        return None
    if isinstance(value, float) and math.isnan(value):
        return None
    if isinstance(value, str):
        value = value.strip()
        if value == "" or value.lower() == "nan":
            return None
    return value


def text(value):
    value = clean(value)
    if value is None:
        return None
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    return str(value)


def code_or_null(value):
    """A LOINC / SNOMED code, or null when the cell holds a placeholder."""
    value = text(value)
    if value is None or value.lower() in PLACEHOLDER_CODES:
        return None
    return value


def number(value, *, where: str):
    value = clean(value)
    if value is None:
        return None
    if isinstance(value, bool):
        raise ConversionError(f"{where}: expected a number, got a boolean.")
    try:
        parsed = float(value)
    except (TypeError, ValueError):
        raise ConversionError(f"{where}: '{value}' is not a number.") from None
    rounded = round(parsed, 6)
    return int(rounded) if rounded.is_integer() else rounded


def integer(value, *, where: str):
    parsed = number(value, where=where)
    if parsed is None:
        return None
    if not float(parsed).is_integer():
        raise ConversionError(f"{where}: '{value}' is not a whole number.")
    return int(parsed)


def flag(value, *, where: str) -> bool:
    parsed = integer(value, where=where)
    if parsed not in (0, 1):
        raise ConversionError(f"{where}: expected 0 or 1, got '{value}'.")
    return parsed == 1


def slug(name: str) -> str:
    """'Age category (reference-range age bands)' -> 'age_category'.

    Every parenthetical is dropped, not only a trailing one, so that
    'Location (unit) type' becomes 'location_type'.
    """
    base = re.sub(r"\([^)]*\)", " ", name)
    base = base.replace("–", " ").replace("—", " ")
    return re.sub(r"[^a-z0-9]+", "_", base.lower()).strip("_")


def renamed(code):
    return CODE_RENAMES.get(code, code)


# --------------------------------------------------------------------------
# Sheets
# --------------------------------------------------------------------------


def convert_value_sets(book: Workbook) -> list[dict]:
    rows = []
    order = defaultdict(int)
    slugs: dict[str, str] = {}
    for record in book.records("Value Sets"):
        name = text(record["Value set (DB column)"])
        where = f"Value Sets row {record['__row']}"
        if name is None:
            raise ConversionError(f"{where}: no value-set name.")
        value_set = slug(name)
        if slugs.setdefault(value_set, name) != name:
            raise ConversionError(f"{where}: '{name}' and '{slugs[value_set]}' share the slug '{value_set}'.")
        local = text(record["Suggested local code"])
        hl7 = text(record["HL7 code"])
        code = local or hl7
        if code is None:
            raise ConversionError(f"{where}: neither a local code nor an HL7 code.")
        order[value_set] += 1
        rows.append({
            "value_set": value_set,
            "value_set_name": name,
            "code": code,
            "display": text(record["Local display"]) or code,
            "hl7_code": hl7,
            "hl7_display": text(record["HL7 display"]),
            "hl7_table": text(record["HL7 table / system"]),
            "fhir_code": text(record["FHIR code"]),
            "fhir_system": text(record["FHIR system"]),
            "notes": text(record["Notes"]),
            "display_order": order[value_set],
        })

    seen = set()
    for row in rows:
        key = (row["value_set"], row["code"])
        if key in seen:
            raise ConversionError(f"Value Sets: duplicate code '{row['code']}' in '{row['value_set']}'.")
        seen.add(key)
    return rows


def convert_catalog(book: Workbook) -> list[dict]:
    tests: dict[str, dict] = {}
    for record in book.records("Lab Catalog (starter)"):
        where = f"Lab Catalog row {record['__row']}"
        code = text(record["Test code"])
        if code is None:
            raise ConversionError(f"{where}: no test code.")

        test = tests.get(code)
        if test is None:
            test = tests[code] = {
                "code": code,
                "name": text(record["Test name"]),
                "loinc_code": None,
                "hl7_section_code": text(record["Category (HL7 0074)"]),
                "hl7_specimen_code": text(record["Specimen (HL7 0487)"]),
                "hl7_nature_code": text(record["result_type (0174)"]),
                "turnaround_time_hours": integer(record["TAT (h)"], where=f"{where} TAT"),
                "display_order": len(tests) + 1,
                "parameters": [],
            }

        if text(record["Parameter name"]) == MULTI_PARAMETER_MARKER:
            test["loinc_code"] = code_or_null(record["LOINC"])
            continue

        source_code = text(record["Parameter code"])
        if source_code is None:
            raise ConversionError(f"{where}: no parameter code.")

        # A single-result test is its own only parameter: the row carries
        # the test's LOINC as well.
        if test["hl7_nature_code"] != "F":
            test["loinc_code"] = code_or_null(record["LOINC"])

        test["parameters"].append({
            "code": renamed(source_code),
            "source_code": source_code,
            "name": text(record["Parameter name"]),
            "loinc_code": code_or_null(record["LOINC"]),
            "hl7_value_type": text(record["data_type (0125)"]),
            "unit_ucum": text(record["Unit (UCUM)"]),
            "reference_low": number(record["Ref low"], where=f"{where} Ref low"),
            "reference_high": number(record["Ref high"], where=f"{where} Ref high"),
            "critical_low": number(record["Critical low"], where=f"{where} Critical low"),
            "critical_high": number(record["Critical high"], where=f"{where} Critical high"),
            "decimal_precision": integer(record["Decimals"], where=f"{where} Decimals") or 0,
            "option_set": text(record["Option set"]),
            "notes": text(record["Notes"]),
            "display_order": len(test["parameters"]) + 1,
        })

    for test in tests.values():
        if not test["parameters"]:
            raise ConversionError(f"Lab Catalog: test {test['code']} has no parameters.")
        if test["hl7_nature_code"] != "F" and len(test["parameters"]) != 1:
            raise ConversionError(f"Lab Catalog: single test {test['code']} has {len(test['parameters'])} parameter rows.")
        if test["hl7_nature_code"] != "F" and test["parameters"][0]["code"] != test["code"]:
            raise ConversionError(f"Lab Catalog: single test {test['code']} has parameter code {test['parameters'][0]['code']}.")
    return list(tests.values())


def convert_ranges(book: Workbook) -> list[dict]:
    rows = []
    for record in book.records("Reference Ranges"):
        where = f"Reference Ranges row {record['__row']} (range #{text(record['Range #'])})"
        source_code = text(record["Parameter code"])
        rows.append({
            "range_number": integer(record["Range #"], where=f"{where} Range #"),
            "test_code": text(record["Test code"]),
            "parameter_code": renamed(source_code),
            "source_parameter_code": source_code,
            "unit_ucum": text(record["Unit (UCUM)"]),
            "sex": text(record["Sex (0001)"]),
            "age_category": text(record["Age category"]),
            "age_min": number(record["Age min"], where=f"{where} Age min"),
            "age_min_unit": text(record["Min unit"]),
            "age_max": number(record["Age max"], where=f"{where} Age max"),
            "age_max_unit": text(record["Max unit"]),
            "reference_low": number(record["Ref low"], where=f"{where} Ref low"),
            "reference_high": number(record["Ref high"], where=f"{where} Ref high"),
            "critical_low": number(record["Critical low"], where=f"{where} Critical low"),
            "critical_high": number(record["Critical high"], where=f"{where} Critical high"),
            "reference_range_text": text(record["OBX-7 text"]),
            "abnormal_when": text(record["abnormal_when"]),
            "status": text(record["Status"]),
            "notes": text(record["Notes"]),
            "__where": where,
        })
    return rows


def convert_panels(book: Workbook) -> list[dict]:
    panels = []
    for record in book.records("Panels (starter)"):
        where = f"Panels row {record['__row']}"
        members = [m.strip() for m in (text(record["Member tests (display_order as listed)"]) or "").split(",")]
        members = [m for m in members if m]
        if not members:
            raise ConversionError(f"{where}: panel has no member tests.")
        panels.append({
            "code": text(record["Panel code"]),
            "name": text(record["Panel name"]),
            "loinc_code": code_or_null(record["LOINC"]),
            "hl7_section_code": text(record["Category (0074)"]),
            "members": members,
            "notes": text(record["Notes"]),
            "display_order": len(panels) + 1,
        })
    return panels


def convert_options(book: Workbook) -> list[dict]:
    options = []
    order = defaultdict(int)
    for record in book.records("Option Sets"):
        where = f"Option Sets row {record['__row']}"
        option_set = text(record["Option set"])
        order[option_set] += 1
        options.append({
            "option_set": option_set,
            "value": text(record["value"]),
            "label": text(record["label"]),
            "is_abnormal": flag(record["is_abnormal"], where=f"{where} is_abnormal"),
            "snomed_code": code_or_null(record["SNOMED CT (OBX-5 coding)"]),
            "hl7_flag": text(record["OBX-8 flag (0078)"]),
            "display_order": order[option_set],
        })
    return options


# --------------------------------------------------------------------------
# Validation
# --------------------------------------------------------------------------


def age_in_days(value, unit):
    return None if value is None else value * AGE_UNITS_IN_DAYS[unit]


def validate_ranges(ranges: list[dict], catalog: list[dict]) -> list[str]:
    errors = []
    parameters = {(t["code"], p["code"]) for t in catalog for p in t["parameters"]}

    for r in ranges:
        where = r["__where"]
        if (r["test_code"], r["parameter_code"]) not in parameters:
            errors.append(f"{where}: {r['test_code']}/{r['parameter_code']} is not in the catalog.")
        if r["sex"] not in SEXES:
            errors.append(f"{where}: sex '{r['sex']}' is not one of any, M, F.")
        for bound in ("min", "max"):
            value, unit = r[f"age_{bound}"], r[f"age_{bound}_unit"]
            if unit is not None and unit not in AGE_UNITS_IN_DAYS:
                errors.append(f"{where}: age {bound} unit '{unit}' is not one of d, mo, a.")
            if (value is None) != (unit is None):
                errors.append(f"{where}: age {bound} and its unit must both be given or both be blank.")
        for low, high in (("reference_low", "reference_high"), ("critical_low", "critical_high")):
            if r[low] is not None and r[high] is not None and r[low] > r[high]:
                errors.append(f"{where}: {low} {r[low]} is above {high} {r[high]}.")
        if errors:
            continue
        lower = age_in_days(r["age_min"], r["age_min_unit"])
        upper = age_in_days(r["age_max"], r["age_max_unit"])
        if lower is not None and upper is not None and lower >= upper:
            errors.append(f"{where}: the age interval is empty.")

    if errors:
        return errors

    # [min, max) intervals within one parameter and sex may not overlap. 'any'
    # rows are compared with each other only: a sex-specific row sitting over
    # an 'any' row is how the more specific range is expressed.
    groups = defaultdict(list)
    for r in ranges:
        lower = age_in_days(r["age_min"], r["age_min_unit"]) or 0.0
        upper = age_in_days(r["age_max"], r["age_max_unit"])
        groups[(r["test_code"], r["parameter_code"], r["sex"])].append(
            (lower, math.inf if upper is None else upper, r["__where"])
        )
    for (test, parameter, sex), intervals in groups.items():
        intervals.sort()
        for (a_low, a_high, a_where), (b_low, b_high, b_where) in zip(intervals, intervals[1:]):
            if b_low < a_high:
                errors.append(f"{test}/{parameter} sex {sex}: {a_where} overlaps {b_where}.")
    return errors


def validate_references(catalog, panels, options) -> list[str]:
    errors = []
    tests = {t["code"] for t in catalog}
    option_sets = {o["option_set"] for o in options}
    for panel in panels:
        for member in panel["members"]:
            if member not in tests:
                errors.append(f"Panel {panel['code']}: member test '{member}' is not in the catalog.")
    for test in catalog:
        for p in test["parameters"]:
            if p["option_set"] and p["option_set"] not in option_sets:
                errors.append(f"{test['code']}/{p['code']}: option set '{p['option_set']}' does not exist.")
            if p["hl7_value_type"] == "CWE" and not p["option_set"]:
                errors.append(f"{test['code']}/{p['code']}: a coded (CWE) parameter needs an option set.")
    return errors


# --------------------------------------------------------------------------


def write(name: str, payload) -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    path = OUT_DIR / name
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")
    print(f"  wrote {path.relative_to(OUT_DIR.parents[3])}")


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print(__doc__.strip().splitlines()[2].strip(), file=sys.stderr)
        return 2

    book = Workbook(Path(argv[1]))
    value_sets = convert_value_sets(book)
    catalog = convert_catalog(book)
    ranges = convert_ranges(book)
    panels = convert_panels(book)
    options = convert_options(book)

    errors = validate_ranges(ranges, catalog) + validate_references(catalog, panels, options)
    if errors:
        print("Refusing to write: the workbook failed validation.\n", file=sys.stderr)
        for error in errors:
            print(f"  - {error}", file=sys.stderr)
        return 1

    for r in ranges:
        del r["__where"]

    source = Path(argv[1]).name
    parameter_count = sum(len(t["parameters"]) for t in catalog)
    write("value_sets.json", {"source": source, "count": len(value_sets), "rows": value_sets})
    write("lab_catalog.json", {"source": source, "test_count": len(catalog), "parameter_count": parameter_count, "tests": catalog})
    write("reference_ranges.json", {"source": source, "count": len(ranges), "rows": ranges})
    write("panels.json", {"source": source, "count": len(panels), "rows": panels})
    write("option_sets.json", {"source": source, "count": len(options), "rows": options})

    print(
        f"\n{len(value_sets)} code values, {len(catalog)} tests / {parameter_count} parameters, "
        f"{len(ranges)} reference ranges, {len(panels)} panels, {len(options)} options."
    )
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main(sys.argv))
    except ConversionError as error:
        print(f"Conversion failed: {error}", file=sys.stderr)
        sys.exit(1)
