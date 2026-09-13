// Sinh mã QR chuyển khoản (VietQR) và nội dung chuyển khoản gắn mã đơn.
//
// Ảnh QR lấy từ img.vietqr.io — chỉ là một đường dẫn ảnh, không cần khoá, không gửi
// dữ liệu khách hàng đi đâu: trong đó chỉ có số tài khoản của mình, số tiền và mã đơn.

export function qrImage({ bankId, account, accountName, amount, code }){
  if(!bankId || !account) return '';
  const p = new URLSearchParams({
    amount: String(Math.round(amount)),
    addInfo: code,
    accountName: accountName || ''
  });
  return 'https://img.vietqr.io/image/' + encodeURIComponent(bankId) + '-'
    + encodeURIComponent(account) + '-compact2.png?' + p;
}

// Tiền về khớp đơn nào: tìm mã đơn trong nội dung chuyển khoản.
// Ngân hàng hay chèn thêm chữ ("CT tu ... DVR2609000 1 ..."), nên bỏ hết ký tự
// không phải chữ-số trước khi dò.
export function matchCode(content){
  const flat = String(content || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  const m = flat.match(/DVR\d{8}/);
  return m ? m[0] : null;
}
