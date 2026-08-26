"""Chuẩn hoá kiểu dữ liệu đọc từ Excel.

Sao kê của mỗi cổng ghi ngày giờ một kiểu ("02-08-2026 22:49:40", "02/08/2026 21:35:05",
hoặc datetime thật khi Excel đã nhận diện được), nên mọi chỗ đọc ngày đều đi qua đây.
"""

from __future__ import annotations

import datetime as _dt
import re
import unicodedata

_DATETIME_FORMATS = (
    "%d-%m-%Y %H:%M:%S",
    "%d/%m/%Y %H:%M:%S",
    "%Y-%m-%d %H:%M:%S",
    "%d-%m-%Y %H:%M",
    "%d/%m/%Y %H:%M",
    "%Y-%m-%d",
    "%d/%m/%Y",
    "%d-%m-%Y",
)

# Excel serial 1 = 1900-01-01, trừ đi 2 để bù lỗi năm nhuận 1900 của Excel.
_EXCEL_EPOCH = _dt.date(1899, 12, 30)

_NUMBER_JUNK = re.compile(r"[^\d\-,.]")


def to_date(value) -> _dt.date | None:
    """Trả về ``date`` từ bất kỳ kiểu ngày nào Excel có thể đưa ra, hoặc None."""
    if value is None or value == "":
        return None
    if isinstance(value, _dt.datetime):
        return value.date()
    if isinstance(value, _dt.date):
        return value
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        # Ngày lưu dạng serial number.
        if 1 <= value <= 200000:
            return _EXCEL_EPOCH + _dt.timedelta(days=int(value))
        return None
    text = str(value).strip()
    if not text:
        return None
    for fmt in _DATETIME_FORMATS:
        try:
            return _dt.datetime.strptime(text, fmt).date()
        except ValueError:
            continue
    return None


def to_int(value) -> int:
    """Số tiền về ``int``. Chuỗi có dấu phân cách nghìn, ô lỗi ``#REF!`` đều thành 0."""
    if value is None or value == "":
        return 0
    if isinstance(value, bool):
        return 0
    if isinstance(value, (int, float)):
        return int(round(value))
    text = _NUMBER_JUNK.sub("", str(value))
    if not text or text in {"-", ".", ","}:
        return 0
    try:
        return int(round(float(_normalize_separators(text))))
    except ValueError:
        return 0


def _normalize_separators(text: str) -> str:
    """Đưa chuỗi số về dạng Python đọc được, xử lý dấu phân cách nhập nhằng.

    Tiền VND trong các file này viết "1.234.567" hoặc "1,234,567" — dấu chấm và
    dấu phẩy đều là phân cách nghìn. Chỗ khó là một dấu duy nhất: "20.000" là hai
    mươi nghìn chứ không phải hai mươi phẩy không. Quy ước: nhóm sau dấu cuối cùng
    dài đúng 3 chữ số thì đó là phân cách nghìn, ngược lại là dấu thập phân.
    """
    dots = text.count(".")
    commas = text.count(",")
    if dots and commas:
        # Dấu đứng sau cùng là dấu thập phân, dấu còn lại là phân cách nghìn.
        if text.rfind(".") > text.rfind(","):
            return text.replace(",", "")
        return text.replace(".", "").replace(",", ".")
    if dots > 1:
        return text.replace(".", "")
    if commas > 1:
        return text.replace(",", "")
    if dots == 1 or commas == 1:
        separator = "." if dots else ","
        after = text.split(separator)[1]
        if len(after) == 3 and after.isdigit():
            return text.replace(separator, "")
        return text.replace(",", ".")
    return text


def clean_text(value) -> str:
    """Bỏ khoảng trắng thừa. Ô trống, ``None``, ``#N/A`` đều thành chuỗi rỗng."""
    if value is None:
        return ""
    text = str(value).strip()
    if text in {"#N/A", "#REF!", "#VALUE!", "-", "0"}:
        return "" if text != "0" else "0"
    return re.sub(r"\s+", " ", text)


def key_text(value) -> str:
    """Khoá so khớp: bỏ dấu, bỏ khoảng trắng thừa, hạ chữ thường.

    Tên điểm trong các file được gõ tay nên hay lệch dấu cách và viết hoa
    ("AM BD KVCM" vs "AM BD  KVCM"), khoá này gộp chúng lại.
    """
    text = clean_text(value)
    if not text:
        return ""
    text = unicodedata.normalize("NFC", text)
    return re.sub(r"\s+", " ", text).casefold()
