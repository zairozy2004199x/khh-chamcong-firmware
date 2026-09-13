// Nội dung email gửi khách. Mỗi mẫu trả về { subject, text, html }.
// HTML viết kiểu email: bảng, màu đặt thẳng vào thẻ — client thư không đọc CSS ngoài.

const vnd = n => new Intl.NumberFormat('vi-VN').format(Math.round(n || 0)) + 'đ';
const ngayVN = s => { const [y,m,d] = String(s||'').split('-'); return d ? d + '/' + m + '/' + y : (s || ''); };
const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function chuyen(o){
  const [a,b] = String(o.flight.route || '').split('-');
  return o.flight.airline + ' ' + o.flight.number + ' · ' + a + ' ' + o.flight.dep
    + ' → ' + b + ' ' + o.flight.arr + ' · ' + ngayVN(o.flight.date);
}

function khung({ tieu, dam, than, mau = '#0B2440', shop, link }){
  return `<div style="margin:0;padding:24px 12px;background:#EEF1F4;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0B2440">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #CCD6E1">
    <tr><td style="background:${mau};color:#E9F0F8;padding:18px 22px">
      <div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;opacity:.75">${esc(shop)}</div>
      <div style="font-size:22px;font-weight:700;margin-top:4px">${esc(tieu)}</div>
    </td></tr>
    ${dam ? `<tr><td style="padding:18px 22px 0"><div style="border-left:4px solid #E8890B;background:#FBF3E8;padding:12px 14px;font-size:15px">${dam}</div></td></tr>` : ''}
    <tr><td style="padding:18px 22px;font-size:14px;line-height:1.6">${than}</td></tr>
    ${link ? `<tr><td style="padding:0 22px 22px"><a href="${esc(link)}" style="display:inline-block;background:#E8890B;color:#1B1000;text-decoration:none;font-weight:700;padding:11px 18px">Xem đơn của tôi</a></td></tr>` : ''}
    <tr><td style="padding:14px 22px;border-top:1px solid #E1E7EE;font-size:12px;color:#6B839C">
      Thư tự động từ ${esc(shop)}. Cần đổi gì thì trả lời thẳng thư này.
    </td></tr>
  </table></div>`;
}

function bang(hang){
  return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px">'
    + hang.map(([k,v]) => `<tr><td style="padding:5px 0;color:#6B839C;width:42%">${esc(k)}</td>`
        + `<td style="padding:5px 0;text-align:right;font-weight:600">${v}</td></tr>`).join('')
    + '</table>';
}

export function soanThu(kind, o, cfg = {}){
  const shop = cfg.shopName || 'Dò Vé Rẻ';
  const link = cfg.publicUrl ? cfg.publicUrl.replace(/\/+$/,'') + '/dat-ve.html?don=' + o.code : '';
  const ten = (o.pax || []).map(p => p.full).filter(Boolean);
  const chungHang = [
    ['Mã đơn', o.code],
    ['Chuyến bay', esc(chuyen(o))],
    ['Hành khách', esc(ten.join(', ') || '—')]
  ];

  if(kind === 'moi'){
    const bank = cfg.bank || {};
    return {
      subject: 'Đơn ' + o.code + ' — chuyển khoản để giữ chỗ',
      text: [
        'Đã nhận yêu cầu đặt vé của anh/chị.', '',
        'Mã đơn: ' + o.code,
        'Chuyến: ' + chuyen(o),
        'Số tiền: ' + vnd(o.money.total) + ' (vé ' + vnd(o.money.fare) + ' + phí dịch vụ ' + vnd(o.money.fee) + ')',
        '', 'Chuyển khoản:',
        '  Ngân hàng   : ' + (bank.bankLabel || bank.bankId || ''),
        '  Số tài khoản: ' + (bank.account || ''),
        '  Chủ tài khoản: ' + (bank.accountName || ''),
        '  Nội dung    : ' + o.code + '   (giữ nguyên, hệ thống dò mã này để khớp tiền)',
        '', 'Giá giữ tới ' + new Date(o.expiresAt).toLocaleString('vi-VN') + '.',
        link ? 'Theo dõi đơn: ' + link : ''
      ].filter(Boolean).join('\n'),
      html: khung({ shop, link, tieu: 'Chuyển khoản để giữ chỗ',
        dam: 'Nội dung chuyển khoản phải giữ nguyên mã <b style="font-family:monospace">' + o.code + '</b> — hệ thống dò mã đó để khớp tiền về.',
        than: bang([
          ...chungHang,
          ['Tiền vé', vnd(o.money.fare)],
          ['Phí dịch vụ', vnd(o.money.fee)],
          ['Tổng phải chuyển', '<span style="color:#B45F05;font-size:18px">' + vnd(o.money.total) + '</span>'],
          ['Ngân hàng', esc((cfg.bank||{}).bankLabel || (cfg.bank||{}).bankId || '—')],
          ['Số tài khoản', esc((cfg.bank||{}).account || '—')],
          ['Chủ tài khoản', esc((cfg.bank||{}).accountName || '—')],
          ['Giữ giá tới', esc(new Date(o.expiresAt).toLocaleString('vi-VN'))]
        ]) })
    };
  }

  if(kind === 'da_nhan_tien'){
    return {
      subject: 'Đã nhận tiền đơn ' + o.code + ' — đang mua vé',
      text: [
        'Đã nhận ' + vnd(o.money.paid) + ' cho đơn ' + o.code + '.', '',
        'Chuyến: ' + chuyen(o),
        'Chúng tôi đang mua vé và sẽ gửi mã đặt chỗ ngay khi xong.',
        o.thieu > 0 ? '\nLưu ý: còn thiếu ' + vnd(o.thieu) + ' so với tổng đơn, nhờ anh/chị chuyển bổ sung.' : '',
        link ? '\nTheo dõi đơn: ' + link : ''
      ].filter(Boolean).join('\n'),
      html: khung({ shop, link, tieu: 'Đã nhận tiền', mau: '#0B6B54',
        dam: o.thieu > 0
          ? 'Còn thiếu <b>' + vnd(o.thieu) + '</b> so với tổng đơn — nhờ anh/chị chuyển bổ sung để chúng tôi mua vé.'
          : 'Chúng tôi đang mua vé. Mã đặt chỗ sẽ gửi ngay khi xuất xong.',
        than: bang([...chungHang, ['Đã nhận', vnd(o.money.paid)], ['Tổng đơn', vnd(o.money.total)]]) })
    };
  }

  if(kind === 'da_xuat_ve'){
    return {
      subject: 'Vé đã xuất — mã đặt chỗ ' + o.pnr + ' (đơn ' + o.code + ')',
      text: [
        'Vé đã xuất.', '',
        'MÃ ĐẶT CHỖ: ' + o.pnr,
        'Chuyến: ' + chuyen(o),
        'Hành khách: ' + (ten.join(', ') || '—'),
        '', 'Làm thủ tục bằng mã đặt chỗ này trên web hoặc app của hãng, hoặc tại quầy.',
        'Mang theo giấy tờ tuỳ thân trùng với tên đã đặt. Ra sân bay trước giờ bay ít nhất 2 tiếng.',
        link ? '\nXem lại đơn: ' + link : ''
      ].filter(Boolean).join('\n'),
      html: khung({ shop, link, tieu: 'Vé đã xuất', mau: '#0B6B54',
        dam: 'Mã đặt chỗ: <b style="font-family:monospace;font-size:20px;letter-spacing:.06em">' + esc(o.pnr) + '</b>',
        than: bang([...chungHang, ['Mã đặt chỗ', '<span style="font-family:monospace">' + esc(o.pnr) + '</span>']])
          + '<p style="margin:14px 0 0;color:#39536E">Làm thủ tục bằng mã đặt chỗ trên web hoặc app của hãng, hoặc tại quầy. '
          + 'Mang giấy tờ tuỳ thân trùng tên đã đặt, có mặt ở sân bay trước giờ bay ít nhất 2 tiếng.</p>' })
    };
  }

  if(kind === 'hoan_tien'){
    return {
      subject: 'Hoàn tiền đơn ' + o.code,
      text: [
        'Đơn ' + o.code + ' không mua được vé nên chúng tôi hoàn lại ' + vnd(o.money.paid) + '.', '',
        'Chuyến: ' + chuyen(o),
        'Tiền về tài khoản đã chuyển, thường trong ngày làm việc. Rất xin lỗi anh/chị.'
      ].filter(Boolean).join('\n'),
      html: khung({ shop, link, tieu: 'Đã hoàn tiền', mau: '#8C3310',
        dam: 'Hoàn lại <b>' + vnd(o.money.paid) + '</b> về tài khoản anh/chị đã chuyển.',
        than: bang([...chungHang, ['Số tiền hoàn', vnd(o.money.paid)]])
          + '<p style="margin:14px 0 0;color:#39536E">Tiền thường về trong ngày làm việc. Rất xin lỗi vì chuyến này không giữ được chỗ.</p>' })
    };
  }

  throw new Error('không có mẫu thư "' + kind + '"');
}
