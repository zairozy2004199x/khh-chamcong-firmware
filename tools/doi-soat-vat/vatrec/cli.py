"""Chạy pipeline từ dòng lệnh: ``python -m vatrec --config config/kh989.json``."""

from __future__ import annotations

import argparse
import sys

from . import config as config_module
from .pipeline import run


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="vatrec", description="Đối soát thu hộ và lập danh sách xuất hoá đơn VAT."
    )
    parser.add_argument("--config", required=True, help="file cấu hình JSON của cơ sở")
    parser.add_argument("--out", required=True, help="file .xlsx đầu ra")
    parser.add_argument(
        "--theo-ngay",
        action="store_true",
        help="mỗi điểm mỗi ngày một dòng hoá đơn, thay vì gộp cả kỳ",
    )
    args = parser.parse_args(argv)

    config = config_module.load(args.config)
    result, invoices = run(config, args.out, theo_ngay=args.theo_ngay or None)

    print(f"Cơ sở {config.co_so} — kỳ {config.ky_tu:%d/%m/%Y} → {config.ky_den:%d/%m/%Y}")
    for stream in result.streams:
        print(f"  {stream:<34} {result.total(stream=stream):>18,d}")
    print(f"  {'TỔNG CỘNG':<34} {result.total():>18,d}")
    dong = "dòng hoá đơn (theo ngày)" if (args.theo_ngay or config.theo_ngay) else "điểm xuất hoá đơn"
    print(f"  {len(invoices)} {dong}, có VAT {sum(i.co_vat for i in invoices):,d}")
    if result.vang_lai:
        print(f"  ⚠ {result.vang_lai_so_giao_dich} giao dịch vãng lai không có mã điểm bán "
              f"({result.vang_lai:,d} đ)")
    if result.unmapped:
        print(f"  ⚠ {len(result.unmapped)} mã điểm bán chưa có trong danh mục "
              f"({sum(u.so_tien for u in result.unmapped):,d} đ) — xem sheet 'Đối soát'")
    print(f"→ {args.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
