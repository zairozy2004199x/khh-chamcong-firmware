"""Sao kê Payoo — tách riêng hai luồng 'Quét mã QR' và 'Thẻ'."""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Cửa hàng", "Ngày giao dịch", "Hình thức thanh toán", "Số tiền thanh toán (₫)"]


def read_payoo(path: str, sheet: str, stream: str | None = None, **_) -> list[Txn]:
    """Đọc sao kê Payoo.

    Số tiền lấy ở cột "Số tiền thanh toán" (tiền khách trả, trước khi trừ phí) —
    đây là số dùng để xuất hoá đơn, khớp tuyệt đối với sheet 'Danh mục tên điểm'.
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    index = column_index(rows[header_row], REQUIRED)
    try:
        ref_column = column_index(rows[header_row], ["Mã giao dịch Payoo"])["Mã giao dịch Payoo"]
    except LookupError:
        ref_column = -1

    prefix = stream or "Payoo"
    out: list[Txn] = []
    for row in rows[header_row + 1 :]:
        code = clean_text(_at(row, index["Cửa hàng"]))
        if not code:
            continue
        hinh_thuc = clean_text(_at(row, index["Hình thức thanh toán"]))
        out.append(
            Txn(
                channel="payoo",
                stream=f"{prefix} - {hinh_thuc}" if hinh_thuc else prefix,
                code=code,
                ngay=to_date(_at(row, index["Ngày giao dịch"])),
                so_tien=to_int(_at(row, index["Số tiền thanh toán (₫)"])),
                ref=clean_text(_at(row, ref_column)) if ref_column >= 0 else "",
            )
        )
    return out


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
