"""Sao kê Payoo — tách riêng hai luồng 'Quét mã QR' và 'Thẻ'."""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Cửa hàng", "Ngày giao dịch", "Hình thức thanh toán", "Số tiền thanh toán (₫)"]


def read_payoo(path: str, sheet: str, stream: str | None = None,
              nguon: str | None = None, **_) -> list[Txn]:
    """Đọc sao kê Payoo.

    Số tiền lấy ở cột "Số tiền thanh toán" (tiền khách trả, trước khi trừ phí) —
    đây là số dùng để xuất hoá đơn, khớp tuyệt đối với sheet 'Danh mục tên điểm'.
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    index = column_index(rows[header_row], REQUIRED)
    ref_columns = _ref_columns(rows[header_row])
    phi_column = column_index(rows[header_row], ["Phí xử lý giao dịch (₫)"], required_all=False).get(
        "Phí xử lý giao dịch (₫)", -1)

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
                nguon=nguon or "",
                stream=f"{prefix} - {hinh_thuc}" if hinh_thuc else prefix,
                code=code,
                ngay=to_date(_at(row, index["Ngày giao dịch"])),
                so_tien=to_int(_at(row, index["Số tiền thanh toán (₫)"])),
                ref=_ref(row, ref_columns),
                nhom=hinh_thuc,
                phi=to_int(_at(row, phi_column)) if phi_column >= 0 else 0,
            )
        )
    return out


# Chống trùng của Payoo phải ghép nhiều cột: "Mã giao dịch Payoo" trong sao kê
# thực tế bỏ trống toàn bộ, còn hai cột có mã thì mỗi cột chỉ phủ một hình thức
# thanh toán — "Mã QR" cho giao dịch quét mã, "Mã chuẩn chi" cho giao dịch thẻ.
# Ghép lại mới phủ hết 100% số dòng và không có mã nào trùng nhau.
REF_COLUMNS = ["Mã giao dịch Payoo", "Mã QR", "Mã chuẩn chi", "Số tham chiếu"]


def _ref_columns(header: list) -> list[int]:
    found = column_index(header, REF_COLUMNS, required_all=False)
    return [found[name] for name in REF_COLUMNS if found.get(name, -1) >= 0]


def _ref(row: list, columns: list[int]) -> str:
    """Mã giao dịch đầu tiên đọc được — rỗng thì bỏ chống trùng cho dòng đó."""
    for column in columns:
        value = clean_text(_at(row, column))
        if value:
            return value
    return ""


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
