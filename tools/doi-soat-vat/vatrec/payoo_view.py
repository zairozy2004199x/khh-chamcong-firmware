"""Bảng lọc dữ liệu Payoo: mã cửa hàng × hình thức thanh toán × ngày.

Sao kê Payoo tải về theo ngày hay theo tháng đều là một danh sách giao dịch thô.
Bảng này gom lại đúng dạng đang dùng để xuất hoá đơn: mỗi cửa hàng tách hai dòng
"Quét mã QR" và "Thẻ", mỗi ngày một cột — chọn ngày nào là đọc thẳng ra số của
ngày đó cho từng cửa hàng.

Bảng dựng thẳng từ giao dịch thô nên chạy được cả khi file chưa kèm danh mục
điểm; lúc đó cột tên điểm và mã misa để trống, còn số tiền vẫn đủ.
"""

from __future__ import annotations

import datetime as _dt
from dataclasses import dataclass, field

from .catalog import Catalog
from .sources import Txn

# Thứ tự hai dòng của mỗi cửa hàng, giữ đúng như bảng đang dùng tay.
THU_TU_NHOM = ["Quét mã QR", "Thẻ"]


@dataclass
class PayooRow:
    """Một dòng của bảng: một cửa hàng, một hình thức thanh toán."""

    code: str
    """Mã cửa hàng Payoo — cột 'Chi nhánh'."""

    nhom: str
    """Hình thức thanh toán: 'Quét mã QR' hay 'Thẻ'."""

    ten_diem: str = ""
    ma_misa: str = ""
    tien: dict[_dt.date, int] = field(default_factory=dict)
    """Số tiền thanh toán theo ngày — đây là số dùng để xuất hoá đơn."""

    phi: dict[_dt.date, int] = field(default_factory=dict)
    """Phí Payoo thu theo ngày. Không vào hoá đơn, chỉ để soát tiền về tài khoản."""

    @property
    def tong_tien(self) -> int:
        return sum(self.tien.values())

    @property
    def tong_phi(self) -> int:
        return sum(self.phi.values())


def payoo_view(txns: list[Txn], catalog: Catalog | None = None) -> list[PayooRow]:
    """Gom giao dịch Payoo thành các dòng của bảng lọc.

    Không lọc theo kỳ báo cáo: bảng này để soi dữ liệu thô nên giữ nguyên mọi
    ngày đọc được, kể cả ngày nằm ngoài kỳ — có gì lệch thì nhìn thấy ngay thay
    vì bị cắt mất.
    """
    rows: dict[tuple[str, str], PayooRow] = {}
    thu_tu_code: dict[str, int] = {}
    for txn in txns:
        if txn.channel != "payoo" or txn.ngay is None:
            continue
        nhom = txn.nhom or "(không rõ)"
        row = rows.get((txn.code, nhom))
        if row is None:
            point = catalog.lookup("payoo", txn.code) if catalog else None
            row = PayooRow(
                code=txn.code,
                nhom=nhom,
                ten_diem=point.ten_diem if point else "",
                ma_misa=point.ma_misa if point else "",
            )
            rows[(txn.code, nhom)] = row
            thu_tu_code.setdefault(txn.code, len(thu_tu_code))
        row.tien[txn.ngay] = row.tien.get(txn.ngay, 0) + txn.so_tien
        row.phi[txn.ngay] = row.phi.get(txn.ngay, 0) + txn.phi

    # Xếp theo thứ tự cửa hàng xuất hiện trong file gốc, không xếp lại theo bảng
    # chữ cái — để bảng ra giống hệt thứ tự anh đang dò tay.
    def sort_key(row: PayooRow) -> tuple:
        thu_tu = THU_TU_NHOM.index(row.nhom) if row.nhom in THU_TU_NHOM else len(THU_TU_NHOM)
        return (thu_tu_code.get(row.code, 0), thu_tu, row.nhom)

    return sorted(rows.values(), key=sort_key)


def payoo_dates(rows: list[PayooRow]) -> list[_dt.date]:
    """Các ngày có phát sinh, tăng dần."""
    found: set[_dt.date] = set()
    for row in rows:
        found.update(row.tien)
    return sorted(found)
