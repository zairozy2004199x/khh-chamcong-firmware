"""Bảng lọc dữ liệu từng file đầu vào: mã điểm bán × nhóm × ngày.

Sao kê tải về đều là một danh sách giao dịch thô. Bảng này gom lại đúng dạng đang
dùng để xuất hoá đơn: mỗi mã điểm bán một dòng (Payoo tách thêm "Quét mã QR" /
"Thẻ", các nguồn khác tách theo luồng tiền), mỗi ngày một cột — chọn ngày nào là
đọc thẳng ra số của ngày đó.

Mỗi file một bảng riêng để so thẳng với chính file gốc rồi mới tin số tổng, thay
vì trộn hết vào một chỗ rồi không biết sai từ đâu.

Bảng dựng thẳng từ giao dịch thô nên chạy được cả khi file chưa kèm danh mục
điểm; lúc đó cột tên điểm và mã misa để trống, còn số tiền vẫn đủ.
"""

from __future__ import annotations

import datetime as _dt
from collections.abc import Callable
from dataclasses import dataclass, field

from .catalog import Catalog
from .sources import Txn

# Thứ tự hai dòng của mỗi cửa hàng Payoo, giữ đúng như bảng đang dùng tay.
THU_TU_NHOM = ["Quét mã QR", "Thẻ"]

TEN_KENH = {
    "qr": "QR VietQR", "payoo": "Payoo", "vnpay": "VNPay",
    "zalo": "Zalo Mini App", "momo": "MoMo",
}


def ten_kenh(channel: str) -> str:
    return TEN_KENH.get(channel, channel)


@dataclass
class LocRow:
    """Một dòng của bảng: một mã điểm bán, một nhóm."""

    channel: str = ""
    """Kênh của mã này — một file có thể chứa nhiều cổng."""

    code: str = ""
    """Mã điểm bán theo cách đánh số của cổng — Payoo là cột 'Chi nhánh'."""

    nhom: str = ""
    """Hình thức thanh toán (Payoo) hoặc luồng tiền (các nguồn khác)."""

    ten_diem: str = ""
    ma_misa: str = ""
    tien: dict[_dt.date, int] = field(default_factory=dict)
    """Số tiền thanh toán theo ngày — đây là số dùng để xuất hoá đơn."""

    phi: dict[_dt.date, int] = field(default_factory=dict)
    """Phí cổng thu theo ngày. Không vào hoá đơn, chỉ để soát tiền về tài khoản."""

    @property
    def tong_tien(self) -> int:
        return sum(self.tien.values())

    @property
    def tong_phi(self) -> int:
        return sum(self.phi.values())


def loc_view(
    txns: list[Txn], chon: Callable[[Txn], bool], catalog: Catalog | None = None
) -> list[LocRow]:
    """Gom giao dịch mà ``chon`` nhận thành các dòng của bảng lọc.

    Không lọc theo kỳ báo cáo: bảng này để soi dữ liệu thô nên giữ nguyên mọi
    ngày đọc được, kể cả ngày nằm ngoài kỳ — có gì lệch thì nhìn thấy ngay thay
    vì bị cắt mất.
    """
    rows: dict[tuple[str, str, str], LocRow] = {}
    thu_tu_code: dict[str, int] = {}
    for txn in txns:
        if not chon(txn) or txn.ngay is None:
            continue
        # Payoo tách theo hình thức thanh toán, các nguồn khác theo luồng tiền.
        nhom = txn.nhom or txn.stream or "(không rõ)"
        row = rows.get((txn.channel, txn.code, nhom))
        if row is None:
            point = catalog.lookup(txn.channel, txn.code) if catalog else None
            if point is None and catalog and txn.code_phu:
                point = catalog.lookup(txn.channel, txn.code_phu)
            row = LocRow(
                channel=txn.channel,
                code=txn.code,
                nhom=nhom,
                ten_diem=point.ten_diem if point else "",
                ma_misa=point.ma_misa if point else "",
            )
            rows[(txn.channel, txn.code, nhom)] = row
            thu_tu_code.setdefault(txn.code, len(thu_tu_code))
        row.tien[txn.ngay] = row.tien.get(txn.ngay, 0) + txn.so_tien
        row.phi[txn.ngay] = row.phi.get(txn.ngay, 0) + txn.phi

    # Xếp theo thứ tự xuất hiện trong file gốc, không xếp lại theo bảng chữ cái —
    # để bảng ra giống hệt thứ tự đang dò tay. Nhưng gom theo điểm trước rồi mới
    # tới mã: một điểm có thể có nhiều mã điểm bán (kênh QR hay gặp), nếu xếp
    # thuần theo mã thì các dòng của cùng một điểm nằm rời nhau, STT nhảy lại và
    # ô STT gộp trong file Excel sẽ sai.
    thu_tu_diem: dict[str, int] = {}
    for row in rows.values():
        thu_tu_diem.setdefault(row.ten_diem or row.code, len(thu_tu_diem))

    def sort_key(row: LocRow) -> tuple:
        thu_tu = THU_TU_NHOM.index(row.nhom) if row.nhom in THU_TU_NHOM else len(THU_TU_NHOM)
        return (thu_tu_diem[row.ten_diem or row.code], thu_tu_code.get(row.code, 0),
                thu_tu, row.nhom)

    return sorted(rows.values(), key=sort_key)


def loc_dates(rows: list[LocRow]) -> list[_dt.date]:
    """Các ngày có phát sinh, tăng dần."""
    found: set[_dt.date] = set()
    for row in rows:
        found.update(row.tien)
    return sorted(found)


KHONG_RO_FILE = "(không rõ file)"


def cac_file(txns: list[Txn], catalog: Catalog | None = None) -> list[tuple[str, list[LocRow]]]:
    """Một bảng lọc cho mỗi file đầu vào, theo thứ tự gặp trong dữ liệu."""
    thu_tu: list[str] = []
    for txn in txns:
        ten = txn.nguon or KHONG_RO_FILE
        if ten not in thu_tu:
            thu_tu.append(ten)
    ra = [
        (nguon, loc_view(txns, lambda txn, n=nguon: (txn.nguon or KHONG_RO_FILE) == n, catalog))
        for nguon in thu_tu
    ]
    return [(nguon, rows) for nguon, rows in ra if rows]
