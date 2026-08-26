"""Sao kê QR VietQR (VPBank) — mỗi tài khoản nhận là một sheet riêng."""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Thời gian TT", "Số tiền đến (VND)", "Trạng thái", "Mã cửa hàng"]
SUCCESS = "Thành công"


def read_qr(path: str, sheet: str, stream: str | None = None, **_) -> list[Txn]:
    """Đọc một sheet sao kê QR.

    Chỉ lấy giao dịch ``Thành công`` — đây đúng là điều kiện file mẫu đang dùng
    (tổng khớp tuyệt đối với các khối 'Tổng QR …' của sheet chia theo mã cửa hàng).
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    index = column_index(rows[header_row], REQUIRED)
    try:
        ref_column = column_index(rows[header_row], ["Mã tham chiếu"])["Mã tham chiếu"]
    except LookupError:
        ref_column = -1

    label = stream or sheet.strip()
    out: list[Txn] = []
    for row in rows[header_row + 1 :]:
        if clean_text(_at(row, index["Trạng thái"])) != SUCCESS:
            continue
        # Mã cửa hàng trống (hay chỉ là dấu "-") là tiền chuyển khoản vãng lai vào
        # tài khoản: có tiền thật nhưng không thuộc điểm bán nào. Vẫn giữ lại để
        # phần tổng hợp đếm riêng — bỏ ở đây thì tiền biến mất không dấu vết.
        code = clean_text(_at(row, index["Mã cửa hàng"]))
        out.append(
            Txn(
                channel="qr",
                stream=label,
                code=code,
                ngay=to_date(_at(row, index["Thời gian TT"])),
                so_tien=to_int(_at(row, index["Số tiền đến (VND)"])),
                ref=clean_text(_at(row, ref_column)) if ref_column >= 0 else "",
            )
        )
    return out


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
