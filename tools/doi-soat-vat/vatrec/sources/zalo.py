"""Đơn hàng Zalo Mini App.

Khác các cổng còn lại, file Zalo không có mã điểm bán: mỗi dòng là một *dòng sản
phẩm* của đơn, và gian hàng nằm trong tên sản phẩm ("VINCOM TIMES CITY - …").
Nên ở đây gom về đơn (một đơn tính một lần) rồi lấy gian hàng từ tên sản phẩm.
"""

from __future__ import annotations

from ..excel import column_index, find_header, read_sheet
from ..normalize import clean_text, to_date, to_int
from .base import Txn

REQUIRED = ["Mã đơn hàng", "Ngày đặt hàng", "Tổng tiền phải trả", "Trạng thái thanh toán"]
PAID = "Đã thanh toán"
CANCELLED = "Đã hủy"


def read_zalo(path: str, sheet: str, stream: str | None = None, **_) -> list[Txn]:
    """Đọc sao kê đơn Zalo Mini App.

    Chỉ lấy đơn đã thanh toán và chưa huỷ. Một mã đơn hàng chỉ tính một lần dù
    trải trên nhiều dòng sản phẩm.
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, REQUIRED)
    header = rows[header_row]
    index = column_index(header, REQUIRED)
    for name in ("Trạng thái đơn hàng", "Tên sản phẩm", "Gắn tên gian hàng", "Chi nhánh"):
        try:
            index[name] = column_index(header, [name])[name]
        except LookupError:
            index[name] = -1

    label = stream or "Zalo mini app"
    seen: set[str] = set()
    out: list[Txn] = []
    for row in rows[header_row + 1 :]:
        ma_don = clean_text(_at(row, index["Mã đơn hàng"]))
        if not ma_don or ma_don in seen:
            continue
        if clean_text(_at(row, index["Trạng thái thanh toán"])) != PAID:
            continue
        if clean_text(_at(row, index["Trạng thái đơn hàng"])) == CANCELLED:
            continue
        seen.add(ma_don)
        out.append(
            Txn(
                channel="zalo",
                stream=label,
                code=_gian_hang(row, index),
                ngay=to_date(_at(row, index["Ngày đặt hàng"])),
                so_tien=to_int(_at(row, index["Tổng tiền phải trả"])),
                ref=ma_don,
            )
        )
    return out


def _gian_hang(row: list, index: dict[str, int]) -> str:
    """Gian hàng của đơn: ưu tiên cột đã gắn sẵn, không có thì cắt từ tên sản phẩm."""
    gan_san = clean_text(_at(row, index["Gắn tên gian hàng"]))
    if gan_san and not gan_san.replace(".", "").isdigit():
        return gan_san
    ten_san_pham = clean_text(_at(row, index["Tên sản phẩm"]))
    if " - " in ten_san_pham:
        return ten_san_pham.split(" - ", 1)[0].strip()
    return ten_san_pham or clean_text(_at(row, index["Chi nhánh"]))


def _at(row: list, index: int):
    return row[index] if 0 <= index < len(row) else None
