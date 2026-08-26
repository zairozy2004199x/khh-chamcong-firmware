"""Danh mục điểm: quy mã kỹ thuật của từng cổng về một điểm xuất hoá đơn.

Mỗi cổng đánh số điểm bán theo cách riêng — QR dùng "Mã cửa hàng", Payoo dùng mã
gian hàng kiểu ``DVGIAITRIKH_FZ_IPH``, VNPay dùng "Mã điểm thu" — nên cần một
bảng quy đổi chung trước khi cộng doanh thu.
"""

from __future__ import annotations

from dataclasses import dataclass, field

from .excel import column_index, find_header, read_sheet
from .normalize import clean_text, key_text


@dataclass(frozen=True)
class Point:
    """Một điểm xuất hoá đơn."""

    ten_diem: str
    ma_misa: str = ""
    khu_vuc: str = ""
    dich_vu: str = ""
    hinh_thuc_hop_tac: str = ""
    phap_nhan: str = ""

    @property
    def key(self) -> str:
        return key_text(self.ten_diem) or key_text(self.ma_misa)


@dataclass
class Catalog:
    """Tra cứu mã kỹ thuật -> điểm xuất hoá đơn, gom theo từng cổng."""

    by_channel: dict[str, dict[str, Point]] = field(default_factory=dict)
    points: dict[str, Point] = field(default_factory=dict)

    def add(self, channel: str, code: str, point: Point) -> None:
        code_key = key_text(code)
        if not code_key or not point.ten_diem:
            return
        self.by_channel.setdefault(channel, {}).setdefault(code_key, point)
        # Bản ghi đầy đủ nhất thắng: các sheet phụ hay bỏ trống khu vực / pháp nhân.
        existing = self.points.get(point.key)
        if existing is None or _filled(point) > _filled(existing):
            self.points[point.key] = point

    def lookup(self, channel: str, code: str) -> Point | None:
        return self.by_channel.get(channel, {}).get(key_text(code))

    def codes(self, channel: str) -> set[str]:
        return set(self.by_channel.get(channel, {}))


def _filled(point: Point) -> int:
    return sum(1 for value in (point.ma_misa, point.khu_vuc, point.dich_vu, point.phap_nhan) if value)


def load_store_codes(path: str, sheet: str) -> tuple[Catalog, list[dict]]:
    """Đọc sheet 'chia theo mã cửa hàng' — nguồn danh mục cho kênh QR.

    Trả về danh mục và danh sách dòng thô (dùng lại khi đối chiếu với file mẫu).
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, ["Mã cửa hàng", "mã điểm xuất hóa đơn"])
    header = rows[header_row]
    index = column_index(
        header,
        ["Tên cửa hàng", "Mã cửa hàng", "Mã điểm bán", "Tên điểm bán", "mã điểm xuất hóa đơn"],
    )
    optional = {}
    for name in ("Khu vực", "Số tk ngân hàng"):
        try:
            optional[name] = column_index(header, [name])[name]
        except LookupError:
            pass

    catalog = Catalog()
    raw: list[dict] = []
    for row in rows[header_row + 1 :]:
        ma_cua_hang = clean_text(_at(row, index["Mã cửa hàng"]))
        if not ma_cua_hang:
            continue
        ten_diem = clean_text(_at(row, index["mã điểm xuất hóa đơn"]))
        point = Point(
            ten_diem=ten_diem,
            khu_vuc=clean_text(_at(row, optional.get("Khu vực", -1))) if "Khu vực" in optional else "",
        )
        if ten_diem:
            catalog.add("qr", ma_cua_hang, point)
        raw.append(
            {
                "ma_cua_hang": ma_cua_hang,
                "ten_cua_hang": clean_text(_at(row, index["Tên cửa hàng"])),
                "ma_diem_ban": clean_text(_at(row, index["Mã điểm bán"])),
                "ten_diem_ban": clean_text(_at(row, index["Tên điểm bán"])),
                "diem_xuat_hoa_don": ten_diem,
                "row": row,
            }
        )
    return catalog, raw


def load_point_directory(path: str, sheet: str, channel: str, code_column: str) -> Catalog:
    """Đọc sheet danh mục điểm của VNPay / Payoo / Zalo.

    ``code_column`` là cột mã kỹ thuật của cổng đó ("Mã điểm thu" với VNPay,
    "Chi nhánh" với Payoo).
    """
    rows = read_sheet(path, sheet)
    header_row = find_header(rows, ["Tên điểm xuất hóa đơn", code_column])
    header = rows[header_row]
    index = column_index(header, ["Tên điểm xuất hóa đơn", code_column])
    for name in ("Mã điểm trên misa thuế", "Khu vực", "Dịch vụ", "Hình thức hợp tác", "Pháp nhân"):
        try:
            index[name] = column_index(header, [name])[name]
        except LookupError:
            index[name] = -1

    catalog = Catalog()
    for row in rows[header_row + 1 :]:
        code = clean_text(_at(row, index[code_column]))
        ten_diem = clean_text(_at(row, index["Tên điểm xuất hóa đơn"]))
        if not code or not ten_diem:
            continue
        catalog.add(
            channel,
            code,
            Point(
                ten_diem=ten_diem,
                ma_misa=clean_text(_at(row, index["Mã điểm trên misa thuế"])),
                khu_vuc=clean_text(_at(row, index["Khu vực"])),
                dich_vu=clean_text(_at(row, index["Dịch vụ"])),
                hinh_thuc_hop_tac=clean_text(_at(row, index["Hình thức hợp tác"])),
                phap_nhan=clean_text(_at(row, index["Pháp nhân"])),
            ),
        )
    return catalog


def merge(*catalogs: Catalog) -> Catalog:
    merged = Catalog()
    for catalog in catalogs:
        for channel, mapping in catalog.by_channel.items():
            for code, point in mapping.items():
                merged.add(channel, code, point)
        for point in catalog.points.values():
            merged.points.setdefault(point.key, point)
            if _filled(point) > _filled(merged.points[point.key]):
                merged.points[point.key] = point
    return merged


def _at(row: list, index: int):
    if index < 0 or index >= len(row):
        return None
    return row[index]
