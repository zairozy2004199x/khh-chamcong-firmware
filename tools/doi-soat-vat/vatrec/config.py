"""Cấu hình cho một cơ sở: lấy dữ liệu ở đâu, danh mục ở đâu, kỳ nào."""

from __future__ import annotations

import datetime as _dt
import json
from dataclasses import dataclass, field
from pathlib import Path

from .invoices import DEFAULT_VAT_RATE, NOI_DUNG_MAC_DINH, TEN_KHACH_MAC_DINH


@dataclass
class SourceSpec:
    kind: str
    file: str
    sheet: str
    stream: str | None = None


@dataclass
class CatalogSpec:
    kind: str
    file: str
    sheet: str
    code_column: str | None = None


@dataclass
class Config:
    co_so: str
    ky_tu: _dt.date
    ky_den: _dt.date
    ngay_hoa_don: _dt.date
    sources: list[SourceSpec] = field(default_factory=list)
    catalogs: list[CatalogSpec] = field(default_factory=list)
    vat_rate: float = DEFAULT_VAT_RATE
    noi_dung: str = NOI_DUNG_MAC_DINH
    ten_khach: str = TEN_KHACH_MAC_DINH
    phap_nhan: str | None = None
    theo_ngay: bool = False
    base_dir: Path = Path(".")

    def resolve(self, file: str) -> str:
        path = Path(file)
        return str(path if path.is_absolute() else self.base_dir / path)


def load(path: str | Path) -> Config:
    path = Path(path)
    data = json.loads(path.read_text(encoding="utf-8"))
    return Config(
        co_so=data["co_so"],
        ky_tu=_date(data["ky_tu"]),
        ky_den=_date(data["ky_den"]),
        ngay_hoa_don=_date(data.get("ngay_hoa_don") or data["ky_den"]),
        sources=[SourceSpec(**item) for item in data.get("sources", [])],
        catalogs=[CatalogSpec(**item) for item in data.get("catalogs", [])],
        vat_rate=data.get("vat_rate", DEFAULT_VAT_RATE),
        noi_dung=data.get("noi_dung", NOI_DUNG_MAC_DINH),
        ten_khach=data.get("ten_khach", TEN_KHACH_MAC_DINH),
        phap_nhan=data.get("phap_nhan"),
        theo_ngay=bool(data.get("theo_ngay", False)),
        base_dir=path.parent,
    )


def _date(value: str) -> _dt.date:
    return _dt.date.fromisoformat(str(value))
