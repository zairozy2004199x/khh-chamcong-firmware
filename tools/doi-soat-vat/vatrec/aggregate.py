"""Gom giao dịch thành bảng điểm xuất hoá đơn × ngày × luồng tiền."""

from __future__ import annotations

import datetime as _dt
from collections import Counter, defaultdict
from dataclasses import dataclass, field

from .catalog import Catalog, Point
from .normalize import clean_text, key_text
from .sources import Txn


@dataclass
class Unmapped:
    """Một mã điểm bán chưa có trong danh mục."""

    channel: str
    code: str
    so_giao_dich: int
    so_tien: int


@dataclass
class Aggregate:
    """Kết quả tổng hợp của một cơ sở."""

    ky_tu: _dt.date | None = None
    ky_den: _dt.date | None = None
    streams: list[str] = field(default_factory=list)
    dates: list[_dt.date] = field(default_factory=list)
    points: dict[str, Point] = field(default_factory=dict)
    cells: dict[tuple[str, str, _dt.date], int] = field(default_factory=dict)
    """(khoá điểm, luồng tiền, ngày) -> số tiền."""

    unmapped: list[Unmapped] = field(default_factory=list)
    ngoai_ky: int = 0
    """Số tiền của giao dịch nằm ngoài kỳ báo cáo (đã bị loại)."""

    vang_lai: int = 0
    """Tiền vào tài khoản nhưng không mang mã điểm bán nào."""

    vang_lai_so_giao_dich: int = 0

    trung_lap: int = 0
    """Số tiền của các bản ghi trùng mã giao dịch (đã bị bỏ)."""

    trung_lap_so_giao_dich: int = 0

    vi_du_trung_lap: list[tuple[str, str, str, int]] = field(default_factory=list)
    """(kênh, luồng, mã giao dịch, số tiền) — tối đa 50 ví dụ để tra ngược."""

    khong_co_ngay: int = 0

    def total(self, point_key: str | None = None, stream: str | None = None) -> int:
        return sum(
            amount
            for (pkey, pstream, _), amount in self.cells.items()
            if (point_key is None or pkey == point_key) and (stream is None or pstream == stream)
        )

    def by_point(self, stream: str | None = None) -> dict[str, int]:
        out: dict[str, int] = defaultdict(int)
        for (pkey, pstream, _), amount in self.cells.items():
            if stream is None or pstream == stream:
                out[pkey] += amount
        return dict(out)

    def by_date(self) -> dict[_dt.date, dict[str, int]]:
        """Ngày -> {luồng tiền: số tiền}. Dùng cho bảng theo dõi hằng ngày."""
        out: dict[_dt.date, dict[str, int]] = defaultdict(lambda: defaultdict(int))
        for (_, stream, ngay), amount in self.cells.items():
            out[ngay][stream] += amount
        return {ngay: dict(streams) for ngay, streams in out.items()}

    def points_per_date(self) -> dict[_dt.date, int]:
        """Số điểm có phát sinh doanh thu trong từng ngày."""
        out: dict[_dt.date, set[str]] = defaultdict(set)
        for (point_key, _, ngay), amount in self.cells.items():
            if amount:
                out[ngay].add(point_key)
        return {ngay: len(keys) for ngay, keys in out.items()}

    def row(self, point_key: str, stream: str) -> dict[_dt.date, int]:
        return {
            date: amount
            for (pkey, pstream, date), amount in self.cells.items()
            if pkey == point_key and pstream == stream
        }


def aggregate(
    txns: list[Txn],
    catalog: Catalog,
    ky_tu: _dt.date | None = None,
    ky_den: _dt.date | None = None,
    chi_phap_nhan: str | None = None,
) -> Aggregate:
    """Quy giao dịch về điểm xuất hoá đơn rồi cộng theo ngày.

    Giao dịch có mã không tra được trong danh mục không bị cộng vào đâu cả — nó
    được gom vào ``unmapped`` để người dùng bổ sung danh mục, chứ không im lặng
    bỏ qua.
    """
    result = Aggregate(ky_tu=ky_tu, ky_den=ky_den)
    unmapped_count: Counter[tuple[str, str]] = Counter()
    unmapped_amount: Counter[tuple[str, str]] = Counter()
    seen_ref: set[tuple[str, str]] = set()
    streams: dict[str, None] = {}
    dates: set[_dt.date] = set()

    for txn in txns:
        streams.setdefault(txn.stream, None)
        if txn.ngay is None:
            result.khong_co_ngay += 1
            continue
        if (ky_tu and txn.ngay < ky_tu) or (ky_den and txn.ngay > ky_den):
            result.ngoai_ky += txn.so_tien
            continue
        # Cùng một mã giao dịch xuất hiện hai lần nghĩa là nó được đọc từ hai
        # sheet (file sao kê thường kèm cả sheet của kỳ cũ). Cộng cả hai thì
        # doanh thu bị đội lên, nên bỏ bản thứ hai và báo lại số đã bỏ.
        if txn.ref:
            if (txn.channel, txn.ref) in seen_ref:
                result.trung_lap += txn.so_tien
                result.trung_lap_so_giao_dich += 1
                if len(result.vi_du_trung_lap) < 50:
                    result.vi_du_trung_lap.append((txn.channel, txn.stream, txn.ref, txn.so_tien))
                continue
            seen_ref.add((txn.channel, txn.ref))

        # Reader đã lọc, nhưng vẫn kiểm lại ở đây để mọi nguồn mới đều an toàn.
        if not clean_text(txn.code):
            result.vang_lai += txn.so_tien
            result.vang_lai_so_giao_dich += 1
            continue

        point = catalog.lookup(txn.channel, txn.code)
        if point is None:
            unmapped_count[(txn.channel, txn.code)] += 1
            unmapped_amount[(txn.channel, txn.code)] += txn.so_tien
            continue
        if chi_phap_nhan and point.phap_nhan and key_text(point.phap_nhan) != key_text(chi_phap_nhan):
            continue

        result.points.setdefault(point.key, catalog.points.get(point.key, point))
        dates.add(txn.ngay)
        cell = (point.key, txn.stream, txn.ngay)
        result.cells[cell] = result.cells.get(cell, 0) + txn.so_tien

    result.streams = list(streams)
    result.dates = sorted(dates)
    result.unmapped = [
        Unmapped(channel=channel, code=code, so_giao_dich=count, so_tien=unmapped_amount[(channel, code)])
        for (channel, code), count in sorted(unmapped_count.items(), key=lambda kv: -unmapped_amount[kv[0]])
    ]
    return result


def period_dates(ky_tu: _dt.date, ky_den: _dt.date) -> list[_dt.date]:
    """Toàn bộ ngày trong kỳ, kể cả ngày không phát sinh doanh thu."""
    span = (ky_den - ky_tu).days
    return [ky_tu + _dt.timedelta(days=offset) for offset in range(span + 1)]
