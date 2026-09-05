"""Kiểu giao dịch chuẩn hoá dùng chung cho mọi cổng."""

from __future__ import annotations

import datetime as _dt
from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class Txn:
    """Một giao dịch đã chuẩn hoá.

    ``code`` là mã kỹ thuật của điểm bán theo cách đánh số của cổng đó; nó được
    tra qua :class:`~vatrec.catalog.Catalog` để ra điểm xuất hoá đơn.
    """

    channel: str
    """Kênh dùng để tra danh mục: ``qr`` | ``payoo`` | ``vnpay`` | ``zalo``."""

    stream: str
    """Luồng tiền hiển thị trên báo cáo, ví dụ 'QR Posh HN' hay 'Payoo - Thẻ'."""

    code: str
    ngay: _dt.date | None
    so_tien: int
    ref: str = ""
    """Mã tham chiếu / mã giao dịch — dùng để phát hiện bản ghi trùng."""

    nguon: str = ""
    """Tên file đã đọc ra giao dịch này, để đối chiếu ngược từng file một."""

    nhom: str = ""
    """Nhóm nhỏ trong cùng một luồng, ví dụ hình thức thanh toán của Payoo."""

    phi: int = 0
    """Phí cổng thu trên giao dịch. Không vào hoá đơn — chỉ để đối chiếu tiền về tài khoản."""
