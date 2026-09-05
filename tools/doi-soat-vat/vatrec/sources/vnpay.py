"""Sao kê VNPay thu hộ."""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Mã điểm thu", "Thời gian GD", "Số tiền sau KM", "Trạng thái"]
SUCCESS = "Thành công"


def read_vnpay(path: str, sheet: str, stream: str | None = None,
              nguon: str | None = None, **_) -> list[Txn]:
    """Đọc sao kê VNPay.

    Doanh thu xuất hoá đơn là "Số tiền sau KM" (số khách thực trả), không phải
    "Số tiền hạch toán thu hộ" — cột hạch toán bị lệch vì có giao dịch VNPay trả
    sang kỳ sau. Số tiền sau KM khớp tuyệt đối với sheet 'Danh mục điểm'.
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    index = column_index(rows[header_row], REQUIRED)
    tuy_chon = column_index(rows[header_row], ["Mã giao dịch", "Chi nhánh"], required_all=False)
    ref_column = tuy_chon.get("Mã giao dịch", -1)
    chi_nhanh_column = tuy_chon.get("Chi nhánh", -1)

    label = stream or "VNPay"
    out: list[Txn] = []
    for row in rows[header_row + 1 :]:
        code = clean_text(_at(row, index["Mã điểm thu"]))
        if not code:
            continue
        if clean_text(_at(row, index["Trạng thái"])) != SUCCESS:
            continue
        out.append(
            Txn(
                channel="vnpay",
                nguon=nguon or "",
                stream=label,
                code=code,
                ngay=to_date(_at(row, index["Thời gian GD"])),
                so_tien=to_int(_at(row, index["Số tiền sau KM"])),
                ref=clean_text(_at(row, ref_column)) if ref_column >= 0 else "",
                nhan=clean_text(_at(row, chi_nhanh_column)) if chi_nhanh_column >= 0 else "",
            )
        )
    return out


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
