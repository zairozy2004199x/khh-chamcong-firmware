"""Sao kê MoMo."""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Thời gian", "Mã đơn hàng", "Trạng thái", "Số tiền", "Mã cửa hàng"]
SUCCESS = "Thành công"


def read_momo(path: str, sheet: str, stream: str | None = None,
              nguon: str | None = None, **_) -> list[Txn]:
    """Đọc sao kê MoMo.

    File MoMo hay đặt hai khối cạnh nhau trên cùng một sheet: khối "MS.…" tổng
    hợp và khối xuất thẳng từ cổng. Vì dò tiêu đề theo tên cột nên tự bắt đúng
    khối xuất thẳng — khối kia không có bộ cột này.

    Đã đối chiếu: tổng khớp tuyệt đối 1.042.790.000 và không lệch một ô nào trên
    17 điểm × 31 ngày của bảng "Tổng Momo T8".
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    index = column_index(rows[header_row], REQUIRED)

    label = stream or "MoMo"
    out: list[Txn] = []
    for row in rows[header_row + 1 :]:
        code = clean_text(_at(row, index["Mã cửa hàng"]))
        if not code:
            continue
        if clean_text(_at(row, index["Trạng thái"])) != SUCCESS:
            continue
        out.append(
            Txn(
                channel="momo",
                nguon=nguon or "",
                stream=label,
                code=code,
                ngay=to_date(_at(row, index["Thời gian"])),
                so_tien=to_int(_at(row, index["Số tiền"])),
                ref=clean_text(_at(row, index["Mã đơn hàng"])),
            )
        )
    return out


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
