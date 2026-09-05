"""Đề xuất điểm xuất hoá đơn cho mã điểm bán chưa có trong danh mục.

Cửa hàng mới phát sinh liên tục, và mỗi cổng đặt mã một kiểu: Payoo ghi
``DVGIAITRIKH_FZ_IPH``, Zalo ghi thẳng tên gian hàng kèm emoji (``🌸 THE LOOP
(IPH)``). Người khai vẫn phải quyết định cuối cùng, nhưng máy đọc được phần lớn
tín hiệu trong chính cái mã đó nên gợi ý sẵn để chỉ còn việc xác nhận.

Gợi ý **không bao giờ tự áp vào**: nó chỉ điền sẵn vào bảng danh mục để người
khai sửa hoặc bỏ. Thà đề xuất sai và bị sửa còn hơn âm thầm gán nhầm tiền vào
hoá đơn của điểm khác.
"""

from __future__ import annotations

import math
import re
import unicodedata
from dataclasses import dataclass

from .catalog import Catalog, Point

# Từ xuất hiện ở quá nửa số mã thì không còn phân biệt được điểm nào với điểm
# nào (ví dụ tiền tố "DVGIAITRIKH" của Payoo), nên bỏ khỏi phép so.
NGUONG_PHO_BIEN = 0.5

# Từ không mang danh tính điểm: mô tả khuyến mãi / loại vé, và loại hình mặt
# bằng ("mall", "mart"). Loại hình mặt bằng hay hiếm trong một danh mục cụ thể
# nên nếu để lại sẽ bị cân là từ đặc trưng và lấn át đúng cái tên địa danh —
# "AEON MALL HUẾ" khớp nhầm sang "FUNFEST AEON MALL BÌNH TÂN" chỉ vì chung chữ
# "mall", trong khi "Huế" mới là chỗ phân biệt.
TU_BO = set("""ve combo mua tang sale km uu dai gia re vui choi tre em nguoi lon thang ngay gio moi hot new vnd d the va cho mall mart plaza center centre sieu thi trung tam cua hang chi nhanh""".split())

# Điểm tối đa của một cách khớp, giảm dần theo độ chắc chắn.
DIEM_TRUNG = 1.0
DIEM_TIEP_DAU = 0.7
DIEM_CHUA = 0.5
# Viết tắt hai chữ là quy ước có thật trong danh mục ("AE" = AEON, "JP", "SC"),
# nên vẫn cho khớp tiếp đầu ngữ ngắn, chỉ tính điểm thấp hơn.
DIEM_TAT = 0.45
DAI_TOI_THIEU = 4
DAI_TAT = 2

# Dưới ngưỡng này thì thà không đề xuất còn hơn đề xuất bừa.
NGUONG_NHAN = 0.34

# Từ không có trong danh mục vẫn phải kéo điểm xuống: khớp được 1 trên 3 từ thì
# không thể chắc bằng khớp cả 3, dù từ khớp có đặc trưng đến đâu.
CAN_TU_LA = 1.0
PHU_TOI_THIEU = 0.35


@dataclass
class GoiY:
    """Một đề xuất cho mã chưa map."""

    point: Point
    diem: float
    """0..1 — càng cao càng chắc."""

    ly_do: str


def bo_dau(text) -> str:
    """Bỏ dấu tiếng Việt và hạ chữ thường — dùng cả để tách từ và để xếp thứ tự.

    Hai bản lõi Python / JavaScript phải xếp giống hệt nhau khi điểm bằng nhau,
    nên chốt một khoá sắp xếp chung thay vì dựa vào cách xếp chữ Việt của từng
    ngôn ngữ.
    """
    return "".join(
        ky_tu for ky_tu in unicodedata.normalize("NFD", str(text or ""))
        if unicodedata.category(ky_tu) != "Mn"
    ).replace("đ", "d").replace("Đ", "D").casefold()


def tach_tu(text: str) -> list[str]:
    """Tách một mã hay tên điểm thành các từ so khớp được.

    Bỏ dấu tiếng Việt và emoji, cắt ở mọi ký tự không phải chữ/số, đồng thời cắt
    giữa cụm chữ và cụm số ("FZ2" -> "fz", "2").
    """
    if not text:
        return []
    tho = re.split(r"[^0-9a-z]+", bo_dau(text))
    ra: list[str] = []
    for phan in tho:
        ra.extend(re.findall(r"[a-z]+|[0-9]+", phan))
    return [tu for tu in ra if len(tu) > 1 and tu not in TU_BO]


def _khop(tu: str, cac_tu: set[str]) -> float:
    if tu in cac_tu:
        return DIEM_TRUNG
    tot = 0.0
    for khac in cac_tu:
        ngan, dai = (tu, khac) if len(tu) <= len(khac) else (khac, tu)
        if not dai.startswith(ngan) and ngan not in dai:
            continue
        if len(ngan) >= DAI_TOI_THIEU:
            tot = max(tot, DIEM_TIEP_DAU if dai.startswith(ngan) else DIEM_CHUA)
        elif len(ngan) >= DAI_TAT and dai.startswith(ngan):
            tot = max(tot, DIEM_TAT)
    return tot


def trong_so(catalog: Catalog) -> dict[str, float]:
    """Cân từ theo độ hiếm trong danh mục.

    Từ có ở hầu hết các điểm ("mall", "an", "go") thì không giúp chọn được điểm
    nào, còn từ chỉ có ở một điểm ("vivocity") thì gần như là chữ ký. Cân theo
    nghịch đảo tần suất để hai loại đó không được tính ngang nhau.
    """
    dem: dict[str, int] = {}
    tong = 0
    for point in catalog.points.values():
        tu_diem = set(tach_tu(point.ten_diem)) | set(tach_tu(point.ma_misa))
        if not tu_diem:
            continue
        tong += 1
        for tu in tu_diem:
            dem[tu] = dem.get(tu, 0) + 1
    if not tong:
        return {}
    return {tu: math.log(tong / so) + 1.0 for tu, so in dem.items()}


def tu_pho_bien(codes: list[str]) -> set[str]:
    """Các từ có mặt ở quá nửa số mã — không dùng để phân biệt điểm được."""
    if len(codes) < 3:
        return set()
    dem: dict[str, int] = {}
    for code in codes:
        for tu in set(tach_tu(code)):
            dem[tu] = dem.get(tu, 0) + 1
    nguong = len(codes) * NGUONG_PHO_BIEN
    return {tu for tu, so in dem.items() if so > nguong}


def goi_y(
    code: str,
    channel: str,
    catalog: Catalog,
    bo_qua: set[str] | None = None,
    so_luong: int = 3,
    can: dict[str, float] | None = None,
) -> list[GoiY]:
    """Xếp hạng các điểm có sẵn theo mức khớp với ``code``.

    ``bo_qua`` là các từ quá phổ biến trong chính lô mã đang xét, tính sẵn bằng
    :func:`tu_pho_bien`; ``can`` là bảng cân theo độ hiếm từ :func:`trong_so`.
    Truyền sẵn cả hai để khỏi tính lại cho từng mã một.
    """
    # Mã đã được khai ở cổng khác là bằng chứng thật, không phải phỏng đoán.
    for kenh_khac, bang in catalog.by_channel.items():
        if kenh_khac == channel:
            continue
        san = bang.get(re.sub(r"\s+", " ", str(code)).strip().casefold())
        if san is not None:
            return [GoiY(point=san, diem=1.0, ly_do=f"mã này đã khai ở kênh {kenh_khac}")]

    tu_ma = [tu for tu in tach_tu(code) if tu not in (bo_qua or set())]
    if not tu_ma:
        return []
    if can is None:
        can = trong_so(catalog)
    can_tu = {tu: can.get(tu, CAN_TU_LA) for tu in tu_ma}
    mau = sum(can_tu.values())
    if mau <= 0:
        return []

    ra: list[GoiY] = []
    for point in catalog.points.values():
        tu_diem = set(tach_tu(point.ten_diem)) | set(tach_tu(point.ma_misa))
        if not tu_diem:
            continue
        khop = [(tu, _khop(tu, tu_diem)) for tu in tu_ma]
        # Hệ số phủ: khớp được càng ít từ trong mã thì càng bớt chắc, kể cả khi
        # từ khớp được là từ đặc trưng nhất.
        so_khop = sum(1 for _, muc in khop if muc)
        phu = PHU_TOI_THIEU + (1 - PHU_TOI_THIEU) * so_khop / len(tu_ma)
        diem = phu * sum(can_tu[tu] * muc for tu, muc in khop) / mau
        if diem >= NGUONG_NHAN:
            chu = [tu for tu, muc in khop if muc]
            ra.append(GoiY(point=point, diem=round(diem, 3), ly_do="khớp chữ " + ", ".join(chu)))

    ra.sort(key=lambda g: (-g.diem, bo_dau(g.point.ten_diem)))
    return ra[:so_luong]
