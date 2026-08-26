"""Reader cho từng cổng thanh toán.

Mọi reader trả về cùng một kiểu ``Txn`` để phần tổng hợp phía sau không cần biết
dữ liệu đến từ cổng nào.
"""

from .base import Txn
from .momo import read_momo
from .payoo import read_payoo
from .qr import read_qr
from .vnpay import read_vnpay
from .zalo import read_zalo

READERS = {
    "qr": read_qr,
    "payoo": read_payoo,
    "vnpay": read_vnpay,
    "zalo": read_zalo,
    "momo": read_momo,
}

__all__ = ["Txn", "READERS", "read_qr", "read_payoo", "read_vnpay", "read_zalo", "read_momo"]
