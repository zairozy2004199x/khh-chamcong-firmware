"""Tách VAT và dựng danh sách hoá đơn cần xuất."""

from __future__ import annotations

import datetime as _dt
from collections import defaultdict
from dataclasses import dataclass

from .aggregate import Aggregate
from .catalog import Point

DEFAULT_VAT_RATE = 0.08
NOI_DUNG_MAC_DINH = "Dịch vụ vui chơi giải trí"
TEN_KHACH_MAC_DINH = "Bán cho người tiêu dùng"


@dataclass
class Invoice:
    """Một dòng hoá đơn của một điểm — cả kỳ, hoặc một ngày khi xuất theo ngày."""

    stt: int
    ngay_hd: _dt.date
    diem: Point
    co_vat: int
    chua_vat: int
    vat: int
    khu_vuc: str
    dich_vu: str
    hinh_thuc_hop_tac: str
    chi_tiet_luong: dict[str, int]
    """Số tiền theo từng luồng, để đối chiếu ngược khi cần."""


def split_vat(co_vat: int, rate: float = DEFAULT_VAT_RATE) -> tuple[int, int]:
    """Tách tổng tiền đã gồm thuế thành (chưa VAT, VAT).

    Làm tròn tới đồng, phần VAT lấy phần dư để tổng luôn khớp tuyệt đối với
    ``co_vat`` — không để lệch 1 đồng do làm tròn hai lần.
    """
    if co_vat == 0:
        return 0, 0
    chua_vat = int(round(co_vat / (1 + rate)))
    return chua_vat, co_vat - chua_vat


def build_invoices(
    result: Aggregate,
    ngay_hoa_don: _dt.date,
    rate: float = DEFAULT_VAT_RATE,
    theo_ngay: bool = False,
) -> list[Invoice]:
    """Dựng danh sách hoá đơn.

    ``theo_ngay=False``: gộp cả kỳ, mỗi điểm một dòng.
    ``theo_ngay=True``: mỗi điểm mỗi ngày một dòng, ngày hoá đơn là ngày phát
    sinh. Sao kê về theo ngày nên chế độ này cho phép xuất hoá đơn hằng ngày
    thay vì đợi hết kỳ.
    """
    order = {
        point_key: index
        for index, (point_key, _) in enumerate(
            sorted(result.points.items(), key=lambda item: (item[1].khu_vuc, item[1].ten_diem.casefold()))
        )
    }

    # (khoá điểm, ngày|None) -> {luồng: số tiền}
    buckets: dict[tuple[str, _dt.date | None], dict[str, int]] = defaultdict(dict)
    for (point_key, stream, ngay), amount in result.cells.items():
        bucket = buckets[(point_key, ngay if theo_ngay else None)]
        bucket[stream] = bucket.get(stream, 0) + amount

    def sort_key(item):
        (point_key, ngay), _ = item
        return (ngay or _dt.date.min, order.get(point_key, 0)) if theo_ngay else (order.get(point_key, 0),)

    invoices: list[Invoice] = []
    for (point_key, ngay), per_stream in sorted(buckets.items(), key=sort_key):
        co_vat = sum(per_stream.values())
        if co_vat == 0:
            continue
        point = result.points[point_key]
        chua_vat, vat = split_vat(co_vat, rate)
        invoices.append(
            Invoice(
                stt=len(invoices) + 1,
                ngay_hd=ngay or ngay_hoa_don,
                diem=point,
                co_vat=co_vat,
                chua_vat=chua_vat,
                vat=vat,
                khu_vuc=point.khu_vuc,
                dich_vu=point.dich_vu,
                hinh_thuc_hop_tac=point.hinh_thuc_hop_tac,
                # Bỏ luồng có số 0 để bản kê chỉ hiện luồng thật sự phát sinh.
                chi_tiet_luong={s: v for s, v in per_stream.items() if v},
            )
        )
    return invoices
