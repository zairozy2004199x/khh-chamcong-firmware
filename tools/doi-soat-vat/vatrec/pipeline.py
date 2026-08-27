"""Ghép các bước lại: đọc → tra danh mục → tổng hợp → hoá đơn → ghi file."""

from __future__ import annotations

from pathlib import Path

from .aggregate import Aggregate, aggregate
from .catalog import (
    Catalog,
    load_momo_catalog,
    load_point_directory,
    load_point_info,
    load_store_codes,
    merge,
)
from .config import Config
from .invoices import Invoice, build_invoices
from .report import write_workbook
from .sources import READERS, Txn


def build_catalog(config: Config) -> Catalog:
    catalogs: list[Catalog] = []
    for spec in config.catalogs:
        path = config.resolve(spec.file)
        if spec.kind == "store_code":
            catalog, _ = load_store_codes(path, spec.sheet)
            catalogs.append(catalog)
        elif spec.kind == "momo":
            catalogs.append(load_momo_catalog(path, spec.sheet))
        elif spec.kind == "point_info":
            catalogs.append(load_point_info(path, spec.sheet))
        else:
            if not spec.code_column:
                raise ValueError(f"danh mục {spec.kind} thiếu 'code_column'")
            catalogs.append(load_point_directory(path, spec.sheet, spec.kind, spec.code_column))
    return merge(*catalogs)


def read_all(config: Config) -> list[Txn]:
    txns: list[Txn] = []
    for spec in config.sources:
        reader = READERS.get(spec.kind)
        if reader is None:
            raise ValueError(f"không có reader cho nguồn {spec.kind!r}")
        # Tên file (không kèm đường dẫn) là nhãn dùng cho tab kiểm từng file.
        nguon = Path(spec.file).name
        txns.extend(reader(config.resolve(spec.file), spec.sheet, spec.stream, nguon))
    return txns


def run(config: Config, out_path: str, theo_ngay: bool | None = None) -> tuple[Aggregate, list[Invoice]]:
    catalog = build_catalog(config)
    txns = read_all(config)
    result = aggregate(txns, catalog, config.ky_tu, config.ky_den, config.phap_nhan)
    if theo_ngay is None:
        theo_ngay = config.theo_ngay
    invoices = build_invoices(result, config.ngay_hoa_don, config.vat_rate, theo_ngay)
    write_workbook(
        out_path,
        config.co_so,
        result,
        invoices,
        config.ky_tu,
        config.ky_den,
        config.noi_dung,
        config.ten_khach,
        config.vat_rate,
        theo_ngay,
    )
    return result, invoices
