"""Ghi file VAT đầu ra và bảng đối soát."""

from __future__ import annotations

import datetime as _dt

from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

from .aggregate import Aggregate, period_dates
from .invoices import split_vat
from .invoices import Invoice

_TIEN = "#,##0"
_NGAY = "dd/mm/yyyy"
_HEADER_FILL = PatternFill("solid", fgColor="DDEBF7")
_TOTAL_FILL = PatternFill("solid", fgColor="FFF2CC")
_WARN_FILL = PatternFill("solid", fgColor="FCE4EC")
_THIN = Side(style="thin", color="BFBFBF")
_BORDER = Border(left=_THIN, right=_THIN, top=_THIN, bottom=_THIN)
_THU = ["Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7", "Chủ nhật"]

DS_COLUMNS = [
    ("STT", 6),
    ("Ngày HĐ", 12),
    ("Số HĐ", 10),
    ("Tên khách hàng", 26),
    ("Mã số thuế khách hàng", 18),
    ("Địa chỉ khách hàng", 18),
    ("Email nhận hóa đơn", 18),
    ("Nội dung xuất hóa đơn", 26),
    ("Số lượng", 9),
    ("ĐVT", 7),
    ("Đơn giá", 12),
    ("Chưa VAT", 15),
    ("VAT", 13),
    ("Có VAT", 15),
    ("Khu vực", 11),
    ("Dịch vụ", 10),
    ("Hình thức hợp tác", 15),
    ("Tên điểm xuất hóa đơn", 32),
    ("Mã điểm trên misa thuế", 24),
]


def write_workbook(
    path: str,
    co_so: str,
    result: Aggregate,
    invoices: list[Invoice],
    ky_tu: _dt.date,
    ky_den: _dt.date,
    noi_dung: str,
    ten_khach: str,
    rate: float = 0.08,
    theo_ngay: bool = False,
) -> None:
    """Ghi toàn bộ file đầu ra: danh sách hoá đơn, bản kê, pivot từng luồng, đối soát."""
    book = Workbook()
    book.remove(book.active)

    _sheet_ds_xuat_hd(book, invoices, noi_dung, ten_khach)
    _sheet_ke_ds(book, invoices, ky_tu, ky_den, noi_dung, ten_khach)
    for stream in result.streams:
        # Luồng không phát sinh đồng nào thì bỏ sheet, khỏi rác file.
        if result.total(stream=stream):
            _sheet_pivot(book, result, stream, ky_tu, ky_den)
    _sheet_theo_ngay(book, result, ky_tu, ky_den, rate)
    _sheet_doi_soat(book, co_so, result, invoices, ky_tu, ky_den, theo_ngay)

    book.save(path)


def _header(sheet, labels: list[str], row: int = 1) -> None:
    for column, label in enumerate(labels, start=1):
        cell = sheet.cell(row=row, column=column, value=label)
        cell.font = Font(bold=True)
        cell.fill = _HEADER_FILL
        cell.border = _BORDER
        cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    sheet.freeze_panes = sheet.cell(row=row + 1, column=1)


def _sheet_ds_xuat_hd(book: Workbook, invoices: list[Invoice], noi_dung: str, ten_khach: str) -> None:
    """Sheet nhập vào Misa — đúng thứ tự cột của file mẫu."""
    sheet = book.create_sheet("DS xuất HĐ MTT")
    _header(sheet, [name for name, _ in DS_COLUMNS])
    for index, (_, width) in enumerate(DS_COLUMNS, start=1):
        sheet.column_dimensions[get_column_letter(index)].width = width

    for offset, invoice in enumerate(invoices):
        row = offset + 2
        values = [
            invoice.stt,
            invoice.ngay_hd,
            None,  # Số HĐ do Misa cấp
            ten_khach,
            None,
            None,
            None,
            noi_dung,
            1,
            "Kỳ",
            None,
            invoice.chua_vat,
            invoice.vat,
            invoice.co_vat,
            invoice.khu_vuc,
            invoice.dich_vu,
            invoice.hinh_thuc_hop_tac,
            invoice.diem.ten_diem,
            invoice.diem.ma_misa,
        ]
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=row, column=column, value=value)
            cell.border = _BORDER
            if column == 2:
                cell.number_format = _NGAY
            elif column in (12, 13, 14):
                cell.number_format = _TIEN

    _total_row(sheet, len(invoices) + 2, len(DS_COLUMNS), {12: "L", 13: "M", 14: "N"}, len(invoices))


def _sheet_ke_ds(
    book: Workbook, invoices: list[Invoice], ky_tu: _dt.date, ky_den: _dt.date, noi_dung: str, ten_khach: str
) -> None:
    """Bản kê chi tiết: thêm cột tách theo từng luồng tiền để đối chiếu ngược."""
    sheet = book.create_sheet("kê ds xuất HĐ MTT")
    streams = sorted({stream for invoice in invoices for stream in invoice.chi_tiet_luong})
    labels = [
        "STT",
        "Tháng",
        "Ngày HĐ",
        "Số HĐ",
        "Tên khách hàng",
        "Nội dung xuất hóa đơn",
        "Tổng TT HĐ htoan Misa",
        "Khu vực",
        "Dịch vụ",
        "Hình thức hợp tác",
        "Tên điểm xuất hóa đơn",
        "Mã điểm trên misa thuế",
        "Pháp nhân",
        "Kỳ",
        *streams,
    ]
    _header(sheet, labels)
    widths = [6, 8, 12, 10, 24, 26, 18, 11, 10, 15, 32, 24, 12, 20]
    for index, width in enumerate(widths, start=1):
        sheet.column_dimensions[get_column_letter(index)].width = width
    for index in range(len(widths) + 1, len(labels) + 1):
        sheet.column_dimensions[get_column_letter(index)].width = 18

    ky = f"{ky_tu:%d/%m/%Y} - {ky_den:%d/%m/%Y}"
    for offset, invoice in enumerate(invoices):
        row = offset + 2
        values = [
            invoice.stt,
            invoice.ngay_hd.month,
            invoice.ngay_hd,
            None,
            ten_khach,
            noi_dung,
            invoice.co_vat,
            invoice.khu_vuc,
            invoice.dich_vu,
            invoice.hinh_thuc_hop_tac,
            invoice.diem.ten_diem,
            invoice.diem.ma_misa,
            invoice.diem.phap_nhan,
            ky,
            *[invoice.chi_tiet_luong.get(stream, 0) for stream in streams],
        ]
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=row, column=column, value=value)
            cell.border = _BORDER
            if column == 3:
                cell.number_format = _NGAY
            elif column == 7 or column > len(widths):
                cell.number_format = _TIEN

    money = {7: "G"}
    money.update({len(widths) + i + 1: get_column_letter(len(widths) + i + 1) for i in range(len(streams))})
    _total_row(sheet, len(invoices) + 2, len(labels), money, len(invoices))


def _sheet_pivot(book: Workbook, result: Aggregate, stream: str, ky_tu: _dt.date, ky_den: _dt.date) -> None:
    """Một sheet cho mỗi luồng tiền: điểm xuất hoá đơn × ngày."""
    sheet = book.create_sheet(_safe_title(stream, book))
    dates = period_dates(ky_tu, ky_den)
    labels = ["STT", "Tên điểm xuất hóa đơn", "Mã điểm trên misa thuế", "Khu vực", "Dịch vụ", "Tổng", *dates]
    _header(sheet, [label if not isinstance(label, _dt.date) else label.strftime("%d/%m") for label in labels])
    for index, width in enumerate([6, 32, 24, 11, 10, 15], start=1):
        sheet.column_dimensions[get_column_letter(index)].width = width
    for index in range(7, len(labels) + 1):
        sheet.column_dimensions[get_column_letter(index)].width = 12

    rows_written = 0
    for point_key, point in sorted(result.points.items(), key=lambda kv: kv[1].ten_diem.casefold()):
        per_date = result.row(point_key, stream)
        total = sum(per_date.values())
        if total == 0:
            continue
        rows_written += 1
        row = rows_written + 1
        values = [rows_written, point.ten_diem, point.ma_misa, point.khu_vuc, point.dich_vu, total]
        values.extend(per_date.get(date, 0) for date in dates)
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=row, column=column, value=value)
            cell.border = _BORDER
            if column >= 6:
                cell.number_format = _TIEN

    money = {index: get_column_letter(index) for index in range(6, len(labels) + 1)}
    _total_row(sheet, rows_written + 2, len(labels), money, rows_written)


def _sheet_theo_ngay(
    book: Workbook, result: Aggregate, ky_tu: _dt.date, ky_den: _dt.date, rate: float
) -> None:
    """Doanh thu từng ngày trong kỳ, tách theo luồng tiền.

    Sao kê về theo ngày nên đây là bảng để theo dõi hằng ngày và phát hiện ngay
    ngày nào hụt số hay thiếu file.
    """
    sheet = book.create_sheet("Tổng theo ngày")
    streams = result.streams
    labels = ["Ngày", "Thứ", "Tổng có VAT", "Chưa VAT", "VAT", "Số điểm phát sinh", *streams]
    _header(sheet, labels)
    for index, width in enumerate([12, 9, 17, 17, 15, 16], start=1):
        sheet.column_dimensions[get_column_letter(index)].width = width
    for index in range(7, len(labels) + 1):
        sheet.column_dimensions[get_column_letter(index)].width = 18

    by_date = result.by_date()
    per_date = result.points_per_date()
    money_columns = {3: "C", 4: "D", 5: "E"}
    money_columns.update({index: get_column_letter(index) for index in range(7, len(labels) + 1)})

    dates = period_dates(ky_tu, ky_den)
    for offset, ngay in enumerate(dates):
        row = offset + 2
        streams_of_day = by_date.get(ngay, {})
        tong = sum(streams_of_day.values())
        chua_vat, vat = split_vat(tong, rate)
        values = [ngay, _THU[ngay.weekday()], tong, chua_vat, vat, per_date.get(ngay, 0)]
        values.extend(streams_of_day.get(stream, 0) for stream in streams)
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=row, column=column, value=value)
            cell.border = _BORDER
            if column == 1:
                cell.number_format = _NGAY
            elif column in money_columns:
                cell.number_format = _TIEN

    _total_row(sheet, len(dates) + 2, len(labels), money_columns, len(dates))


def _sheet_doi_soat(
    book: Workbook, co_so: str, result: Aggregate, invoices: list[Invoice],
    ky_tu: _dt.date, ky_den: _dt.date, theo_ngay: bool = False
) -> None:
    """Bảng đối soát: tổng theo luồng, mã chưa map, cảnh báo trùng / ngoài kỳ."""
    sheet = book.create_sheet("Đối soát")
    sheet.column_dimensions["A"].width = 34
    sheet.column_dimensions["B"].width = 40
    sheet.column_dimensions["C"].width = 18
    sheet.column_dimensions["D"].width = 16

    row = 1

    def put(a=None, b=None, c=None, d=None, bold=False, fill=None, money=False):
        nonlocal row
        for column, value in enumerate((a, b, c, d), start=1):
            cell = sheet.cell(row=row, column=column, value=value)
            if bold:
                cell.font = Font(bold=True)
            if fill:
                cell.fill = fill
            if money and column in (3, 4):
                cell.number_format = _TIEN
        row += 1

    put(f"ĐỐI SOÁT — cơ sở {co_so}", bold=True, fill=_HEADER_FILL)
    put("Kỳ báo cáo", f"{ky_tu:%d/%m/%Y} → {ky_den:%d/%m/%Y}")
    put("Kiểu xuất hoá đơn", "Theo từng ngày" if theo_ngay else "Gộp cả kỳ")
    put()

    put("Tổng theo luồng tiền", "", "Số tiền", bold=True, fill=_HEADER_FILL)
    for stream in result.streams:
        put("", stream, result.total(stream=stream), money=True)
    put("", "TỔNG CỘNG", result.total(), bold=True, fill=_TOTAL_FILL, money=True)
    put()

    put("Hoá đơn", "", "Số tiền", bold=True, fill=_HEADER_FILL)
    put("", f"Số điểm xuất hoá đơn: {len(invoices)}")
    put("", "Chưa VAT", sum(item.chua_vat for item in invoices), money=True)
    put("", "VAT", sum(item.vat for item in invoices), money=True)
    put("", "Có VAT", sum(item.co_vat for item in invoices), bold=True, fill=_TOTAL_FILL, money=True)
    lech = result.total() - sum(item.co_vat for item in invoices)
    put("", "Lệch so với tổng luồng tiền", lech, fill=None if lech == 0 else _WARN_FILL, money=True)
    put()

    put("Cảnh báo", "", "Số lượng", "Số tiền", bold=True, fill=_HEADER_FILL)
    put("", "Giao dịch không đọc được ngày", result.khong_co_ngay, None, fill=None if not result.khong_co_ngay else _WARN_FILL)
    put("", "Tiền của giao dịch ngoài kỳ (đã loại)", None, result.ngoai_ky, fill=None if not result.ngoai_ky else _WARN_FILL, money=True)
    put("", "Giao dịch trùng mã (đã bỏ bản thứ hai)", result.trung_lap_so_giao_dich,
        result.trung_lap, fill=None if not result.trung_lap else _WARN_FILL, money=True)
    put("", "Tiền vãng lai (không có mã điểm bán)", result.vang_lai_so_giao_dich, result.vang_lai,
        fill=None if not result.vang_lai else _WARN_FILL, money=True)
    put("", "Tiền của điểm thuộc pháp nhân khác (đã loại)", None, result.loai_khac_phap_nhan,
        fill=None if not result.loai_khac_phap_nhan else _WARN_FILL, money=True)
    put()

    put("Mã điểm bán chưa có trong danh mục", "", "Số GD", "Số tiền", bold=True, fill=_HEADER_FILL)
    if not result.unmapped:
        put("", "(không có — mọi mã đều tra được)")
    for item in result.unmapped:
        put(item.channel, item.code, item.so_giao_dich, item.so_tien, fill=_WARN_FILL, money=True)
    put()

    if result.trung_lap_so_giao_dich:
        put("Giao dịch trùng mã đã bị bỏ", "", "", "Số tiền", bold=True, fill=_HEADER_FILL)
        put("", "Thường do chọn nhầm sheet của kỳ cũ trong cùng một file.")
        for channel, stream, ref, so_tien in result.vi_du_trung_lap:
            put(f"{channel} / {stream}", ref, None, so_tien, fill=_WARN_FILL, money=True)
        con_lai = result.trung_lap_so_giao_dich - len(result.vi_du_trung_lap)
        if con_lai > 0:
            put("", f"… và {con_lai} giao dịch nữa")


def _total_row(sheet, row: int, width: int, money_columns: dict[int, str], data_rows: int) -> None:
    if data_rows <= 0:
        return
    cell = sheet.cell(row=row, column=1, value="TỔNG")
    cell.font = Font(bold=True)
    cell.fill = _TOTAL_FILL
    for column in range(2, width + 1):
        sheet.cell(row=row, column=column).fill = _TOTAL_FILL
    for column, letter in money_columns.items():
        cell = sheet.cell(row=row, column=column, value=f"=SUM({letter}2:{letter}{row - 1})")
        cell.font = Font(bold=True)
        cell.number_format = _TIEN
        cell.fill = _TOTAL_FILL


def _safe_title(name: str, book: Workbook) -> str:
    """Tên sheet Excel: tối đa 31 ký tự, không chứa ``[]:*?/\\``, không trùng."""
    cleaned = "".join(" " if character in "[]:*?/\\" else character for character in name).strip()
    cleaned = (cleaned or "Luồng")[:31]
    if cleaned not in book.sheetnames:
        return cleaned
    for suffix in range(2, 100):
        candidate = f"{cleaned[: 31 - len(str(suffix)) - 1]} {suffix}"
        if candidate not in book.sheetnames:
            return candidate
    raise ValueError(f"không đặt được tên sheet cho {name!r}")
