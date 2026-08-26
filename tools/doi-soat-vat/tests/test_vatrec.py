"""Test cho lõi đối soát (bản Python).

Chạy: ``python3 tests/test_vatrec.py`` từ thư mục ``tools/doi-soat-vat``.
Không cần pytest — chỉ dùng assert để chạy được ở mọi máy.
"""

import datetime as dt
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from vatrec.aggregate import aggregate  # noqa: E402
from vatrec.catalog import Catalog, Point  # noqa: E402
from vatrec.excel import column_index, date_blocks, find_header  # noqa: E402
from vatrec.invoices import build_invoices, split_vat  # noqa: E402
from vatrec.normalize import clean_text, key_text, to_date, to_int  # noqa: E402
from vatrec.sources import Txn  # noqa: E402

_passed = 0
_failed: list[str] = []


def check(name: str, condition: bool, detail: str = "") -> None:
    global _passed
    if condition:
        _passed += 1
    else:
        _failed.append(f"{name}{': ' + detail if detail else ''}")


# ------------------------------------------------------------------ normalize

def test_to_date():
    check("ngày kiểu VN có giờ", to_date("02-08-2026 22:49:40") == dt.date(2026, 8, 2))
    check("ngày kiểu VN gạch chéo", to_date("02/08/2026 21:35:05") == dt.date(2026, 8, 2))
    check("ngày kiểu ISO", to_date("2026-08-02 21:49:56") == dt.date(2026, 8, 2))
    check("datetime sẵn", to_date(dt.datetime(2026, 8, 2, 1, 2)) == dt.date(2026, 8, 2))
    check("ô trống", to_date("") is None and to_date(None) is None)
    check("chữ không phải ngày", to_date("PaymentForOrder") is None)
    check("ngày vô lý bị loại", to_date("45-13-2026 10:00:00") is None)


def test_to_int():
    check("số nguyên", to_int(20000) == 20000)
    check("phân cách nghìn kiểu VN", to_int("1.234.567") == 1234567)
    check("phân cách nghìn kiểu Anh", to_int("1,234,567") == 1234567)
    check("ô lỗi công thức", to_int("#REF!") == 0 and to_int("#N/A") == 0)
    check("ô trống", to_int(None) == 0 and to_int("") == 0)
    check("số thực làm tròn", to_int(17474008.4) == 17474008)
    check("chuỗi có ký tự tiền tệ", to_int("20.000 ₫") == 20000)


def test_text():
    check("gộp khoảng trắng", clean_text("AM BD   KVCM") == "AM BD KVCM")
    check("dấu gạch coi như rỗng", clean_text("-") == "")
    check("khoá bỏ hoa thường", key_text("AM BD  KVCM") == key_text("am bd kvcm"))


# ---------------------------------------------------------------------- Excel

def _sheet():
    return [
        ["Báo cáo", None, None, None],
        [None, None, "tổng", 123],
        ["Mã cửa hàng", "mã điểm xuất hóa đơn", "Tổng", dt.datetime(2026, 8, 1)],
        ["ABC", "Điểm A", 100, 100],
    ]


def test_find_header():
    rows = _sheet()
    check("dò đúng dòng tiêu đề", find_header(rows, ["Mã cửa hàng", "mã điểm xuất hóa đơn"]) == 2)
    check("dò không phân biệt hoa thường", find_header(rows, ["MÃ CỬA HÀNG"]) == 2)
    try:
        find_header(rows, ["Cột không tồn tại"])
        check("thiếu cột thì báo lỗi", False, "đáng lẽ phải ném LookupError")
    except LookupError:
        check("thiếu cột thì báo lỗi", True)


def test_column_index():
    index = column_index(_sheet()[2], ["Mã cửa hàng", "Tổng"])
    check("ánh xạ cột", index == {"Mã cửa hàng": 0, "Tổng": 2}, str(index))


def test_date_blocks():
    header = ["STT", "Tổng QR A", dt.datetime(2026, 8, 1), dt.datetime(2026, 8, 2),
              "Tổng QR B", dt.datetime(2026, 8, 1)]
    blocks = date_blocks(header)
    check("tách đúng số khối", len(blocks) == 2, str(blocks))
    check("nhãn khối 1", blocks[0][0] == "Tổng QR A")
    check("khối 1 có 2 ngày", len(blocks[0][1]) == 2)
    check("nhãn khối 2", blocks[1][0] == "Tổng QR B")


# ------------------------------------------------------------------------ VAT

def test_split_vat():
    check("tách VAT theo file mẫu", split_vat(37750000, 0.08) == (34953704, 2796296))
    check("cộng lại đúng bằng tổng", all(
        sum(split_vat(value, 0.08)) == value
        for value in range(1, 200001, 7)
    ), "làm tròn làm lệch tổng")
    check("số 0", split_vat(0, 0.08) == (0, 0))
    check("thuế suất 0%", split_vat(1000, 0) == (1000, 0))


# ------------------------------------------------------------------ aggregate

def _catalog():
    catalog = Catalog()
    catalog.add("qr", "SHOP1", Point(ten_diem="Điểm A", khu_vuc="Hà Nội", phap_nhan="KH cũ"))
    catalog.add("qr", "SHOP2", Point(ten_diem="Điểm B", khu_vuc="HCM", phap_nhan="KH mới"))
    return catalog


def _txn(code, day, amount, ref="", stream="QR", channel="qr"):
    return Txn(channel=channel, stream=stream, code=code,
               ngay=dt.date(2026, 8, day) if day else None, so_tien=amount, ref=ref)


def test_aggregate_basic():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("SHOP1", 2, 200, "r2"), _txn("SHOP2", 1, 50, "r3")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("tổng đúng", result.total() == 350, str(result.total()))
    check("hai điểm", len(result.points) == 2)
    check("cộng theo ngày", result.row(key_text("Điểm A"), "QR") ==
          {dt.date(2026, 8, 1): 100, dt.date(2026, 8, 2): 200})


def test_aggregate_loai_ngoai_ky():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"),
         Txn(channel="qr", stream="QR", code="SHOP1", ngay=dt.date(2026, 7, 31), so_tien=999, ref="r9")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("loại giao dịch ngoài kỳ", result.total() == 100)
    check("báo lại số tiền ngoài kỳ", result.ngoai_ky == 999)


def test_aggregate_vang_lai():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("", 1, 300, "r2"), _txn("-", 2, 200, "r3")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("vãng lai không vào doanh thu điểm", result.total() == 100)
    check("cộng đúng tiền vãng lai", result.vang_lai == 500, str(result.vang_lai))
    check("đếm đúng giao dịch vãng lai", result.vang_lai_so_giao_dich == 2)


def test_aggregate_chua_map():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("LA1", 1, 700, "r2"), _txn("LA1", 2, 300, "r3")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("mã lạ không lọt vào doanh thu", result.total() == 100)
    check("gom mã lạ", len(result.unmapped) == 1 and result.unmapped[0].code == "LA1")
    check("cộng đúng tiền mã lạ", result.unmapped[0].so_tien == 1000)
    check("đếm đúng giao dịch mã lạ", result.unmapped[0].so_giao_dich == 2)


def test_aggregate_bo_trung_ma():
    """Cùng một mã giao dịch đọc từ hai sheet chỉ được tính một lần."""
    result = aggregate(
        [_txn("SHOP1", 1, 100, "FT001", stream="Sheet kỳ này"),
         _txn("SHOP1", 1, 100, "FT001", stream="Sheet kỳ cũ"),
         _txn("SHOP1", 2, 50, "FT002")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("không cộng hai lần", result.total() == 150, str(result.total()))
    check("báo lại số tiền trùng", result.trung_lap == 100)
    check("đếm đúng giao dịch trùng", result.trung_lap_so_giao_dich == 1)
    check("giữ ví dụ để tra ngược", result.vi_du_trung_lap[0][2] == "FT001")


def test_aggregate_trung_ma_khac_kenh():
    """Hai cổng khác nhau tình cờ trùng mã thì vẫn là hai giao dịch thật."""
    result = aggregate(
        [_txn("SHOP1", 1, 100, "X1", channel="qr"),
         Txn(channel="payoo", stream="Payoo", code="SHOP1", ngay=dt.date(2026, 8, 1),
             so_tien=70, ref="X1")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    check("không nhầm giữa hai cổng", result.trung_lap == 0)


def test_aggregate_loc_phap_nhan():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("SHOP2", 1, 900, "r2")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31), chi_phap_nhan="KH cũ",
    )
    check("chỉ lấy pháp nhân đã chọn", result.total() == 100)


def test_aggregate_khong_co_ngay():
    result = aggregate(
        [_txn("SHOP1", None, 100, "r1")], _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31)
    )
    check("đếm giao dịch không đọc được ngày", result.khong_co_ngay == 1)
    check("không tính vào doanh thu", result.total() == 0)


# ------------------------------------------------------------------- invoices

def test_build_invoices():
    result = aggregate(
        [_txn("SHOP1", 1, 37750000, "r1"), _txn("SHOP2", 1, 0, "r2")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    invoices = build_invoices(result, dt.date(2026, 8, 31), 0.08)
    check("bỏ điểm không doanh thu", len(invoices) == 1, str(len(invoices)))
    check("có VAT đúng", invoices[0].co_vat == 37750000)
    check("chưa VAT đúng", invoices[0].chua_vat == 34953704)
    check("VAT đúng", invoices[0].vat == 2796296)
    check("giữ chi tiết theo luồng", invoices[0].chi_tiet_luong == {"QR": 37750000})


def test_build_invoices_theo_ngay():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("SHOP1", 2, 200, "r2"), _txn("SHOP2", 2, 50, "r3")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    invoices = build_invoices(result, dt.date(2026, 8, 31), 0.08, theo_ngay=True)
    check("mỗi điểm mỗi ngày một dòng", len(invoices) == 3, str(len(invoices)))
    check("ngày hoá đơn là ngày phát sinh", invoices[0].ngay_hd == dt.date(2026, 8, 1))
    check("xếp theo ngày tăng dần",
          [i.ngay_hd for i in invoices] == [dt.date(2026, 8, 1), dt.date(2026, 8, 2), dt.date(2026, 8, 2)])
    check("tổng không đổi so với gộp kỳ", sum(i.co_vat for i in invoices) == 350)

    gop = build_invoices(result, dt.date(2026, 8, 31), 0.08)
    check("gộp kỳ vẫn ra 2 dòng", len(gop) == 2)
    check("gộp kỳ cùng tổng", sum(i.co_vat for i in gop) == 350)


def test_by_date():
    result = aggregate(
        [_txn("SHOP1", 1, 100, "r1"), _txn("SHOP2", 1, 50, "r2"), _txn("SHOP1", 3, 200, "r3")],
        _catalog(), dt.date(2026, 8, 1), dt.date(2026, 8, 31),
    )
    by_date = result.by_date()
    check("cộng đúng theo ngày", sum(by_date[dt.date(2026, 8, 1)].values()) == 150)
    check("ngày không phát sinh thì không có khoá", dt.date(2026, 8, 2) not in by_date)
    per_date = result.points_per_date()
    check("đếm đúng số điểm trong ngày", per_date[dt.date(2026, 8, 1)] == 2)
    check("ngày chỉ một điểm", per_date[dt.date(2026, 8, 3)] == 1)


# ----------------------------------------------------------------------- chạy

def main() -> int:
    for name, function in sorted(globals().items()):
        if name.startswith("test_") and callable(function):
            function()
    print(f"{_passed} kiểm tra đạt, {len(_failed)} lỗi")
    for failure in _failed:
        print(f"  ✗ {failure}")
    return 1 if _failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
