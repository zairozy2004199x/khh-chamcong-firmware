/* Báo cáo doanh thu FABi — giao diện. Số liệu lấy từ REST của plugin.
   Dùng được ở 3 chỗ: trang quản trị, shortcode [khh_doanh_thu], và trong Nền tảng K&H. */
(function () {
  'use strict';

  var CF = window.KHH_DT || {};
  var VND = new Intl.NumberFormat('vi-VN');

  function tien(n) { return VND.format(Math.round(n || 0)) + ' ₫'; }
  function nguyen(n) { return VND.format(Math.round(n || 0)); }
  function phan(n) { return (Math.round((n || 0) * 10) / 10).toString().replace('.', ',') + '%'; }
  function tienGon(n) {
    n = n || 0; var a = Math.abs(n);
    function cat(x) { return x.replace(/\.0$/, '').replace('.', ','); }
    if (a >= 1e9) return cat((n / 1e9).toFixed(a >= 1e10 ? 0 : 1)) + ' tỷ';
    if (a >= 1e6) return cat((n / 1e6).toFixed(a >= 1e7 ? 0 : 1)) + ' tr';
    if (a >= 1e3) return Math.round(n / 1e3) + ' ng';
    return String(Math.round(n));
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function el(t, c, x) { var e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; }
  function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function doi(s, n) { var d = new Date(s + 'T00:00:00'); d.setDate(d.getDate() + n); return ymd(d); }
  function cach(a, b) { return Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000); }
  function nhanNgay(s) { var p = s.split('-'); return p[2] + '/' + p[1]; }
  function ngayVN(s) { return s ? s.split('-').reverse().join('/') : ''; }
  function ngayDu(s) {
    var d = new Date(s + 'T00:00:00');
    var t = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'][d.getDay()];
    return t + ', ' + ngayVN(s);
  }
  function cong(a, f) { return a.reduce(function (s, x) { return s + (f(x) || 0); }, 0); }
  function z24() { var a = []; for (var i = 0; i < 24; i++) a.push(0); return a; }

  var S = { cf: null, ngay: [], ky: '7', tu: '', den: '', ch: '*', xepMon: 'r', dangTai: false,
            tab: 'doanhthu', nhapNgay: '', nhapCH: '',
            /* Kỳ RIÊNG cho tab Đối soát. Dùng chung kỳ với tab Doanh thu thì ô chọn nằm ở tab kia,
               người đang đứng ở Đối soát không thấy gì để bấm — mà đây mới là tab người ta ngồi
               lâu nhất, và là tab cần đổi ngày nhiều nhất. */
            ds: { ky: '7', tu: '', den: '', ch: '*', chiCanh: false },
            lich: { thang: '', mo: false } };
  var G = null; // gốc DOM

  /* ---------------- gọi máy chủ ---------------- */
  /* Thẻ phiên của người vào bằng PIN chấm công. Để ở localStorage và gửi bằng header, KHÔNG để
     ở cookie: cookie thì mọi biểu mẫu trên site đều mang theo, còn header thì chỉ mã này gửi —
     nên không có đường cho trang khác mượn tay người dùng gọi hộ. */
  var KHOA_THE = 'khh_dt_phien';
  function the() { try { return window.localStorage.getItem(KHOA_THE) || ''; } catch (e) { return ''; } }
  function datThe(t) {
    try { if (t) window.localStorage.setItem(KHOA_THE, t); else window.localStorage.removeItem(KHOA_THE); }
    catch (e) { /* trình duyệt chặn localStorage: vẫn dùng được tới khi tải lại trang */ }
    THE_TAM = t || '';
  }
  var THE_TAM = '';
  function theHienCo() { return THE_TAM || the(); }

  function api(duong, tuyChon) {
    tuyChon = tuyChon || {};
    tuyChon.credentials = 'same-origin';
    tuyChon.headers = tuyChon.headers || {};
    tuyChon.headers['X-WP-Nonce'] = CF.nonce;
    var t = theHienCo();
    if (t) tuyChon.headers['X-KHH-Phien'] = t;
    return fetch(CF.rest + duong, tuyChon).then(function (r) {
      return r.text().then(function (t) {
        var j = null;
        try { j = JSON.parse(t); } catch (e) { j = null; }
        if (j === null) {
          // Máy chủ trả HTML: gần như luôn là bị chặn trước khi tới WordPress.
          var vi = r.status === 413 ? 'File vượt quá mức máy chủ cho nhận một lần.'
            : r.status === 403 ? 'Máy chủ (hoặc plugin bảo mật) chặn yêu cầu này.'
            : r.status >= 500 ? 'Máy chủ gặp lỗi khi xử lý (mã ' + r.status + ').'
            : 'Máy chủ trả về trang HTML thay vì dữ liệu (mã ' + r.status + ').';
          throw new Error(vi + ' Anh chụp màn hình gửi em, hoặc xem nhật ký lỗi của hosting.');
        }
        if (!r.ok) throw new Error(j.message || ('Máy chủ trả mã ' + r.status));
        return j;
      });
    });
  }

  /* ---------------- kỳ báo cáo ---------------- */
  /* `o` là ô giữ kỳ — S cho tab Doanh thu, S.ds cho tab Đối soát. */
  function tinhKyCua(o) {
    var cf = S.cf || {};
    var cuoi = cf.den_ngay || ymd(new Date());
    var dau = cf.tu_ngay || cuoi;
    var tu, den = cuoi;
    if (o.ky === 'all') tu = dau;
    else if (o.ky === 'thang') tu = cuoi.slice(0, 8) + '01';
    else if (o.ky === 'tay') {
      tu = o.tu || dau; den = o.den || cuoi;
      if (tu > den) { var t = tu; tu = den; den = t; }
    } else {
      var n = parseInt(o.ky, 10);
      tu = doi(cuoi, -(n - 1));
      if (tu < dau) tu = dau;
    }
    o.tu = tu; o.den = den;
    return { tu: tu, den: den };
  }

  function tinhKy() { return tinhKyCua(S); }

  /* ═══ Ô NGÀY: ĐỪNG CHẠY LÚC NGƯỜI TA ĐANG GÕ ═══════════════════════════════════════
     Anh Thắng 18/09/2026: *"cứ bấm sửa số ngày nó trắng xíu trong nhảy ra, sao không bấm số,
     rồi chọn mới chạy"*.

     `<input type="date">` bắn `change` NGAY GIỮA LÚC GÕ, không đợi gõ xong:
       · xoá một ô để sửa -> giá trị thành RỖNG -> `change` -> đi hỏi máy chủ một khoảng rỗng;
       · gõ tiếp ngày mới -> vừa đủ một ngày hợp lệ là `change` lần nữa, dù còn đang gõ tháng.
     Mỗi lần như thế là một lượt tải, mà lượt tải lại xoá trắng cả màn -> "trắng xíu rồi nhảy
     ra". Ba luật ở đây:

       1. Giá trị chưa đủ một ngày (rỗng, gõ dở) thì KHÔNG làm gì. Đây là luật quan trọng nhất
          — rỗng mà vẫn chạy là hỏi máy chủ một khoảng vô nghĩa.
       2. Đủ rồi thì vẫn chờ một nhịp, để gõ nốt ngày/tháng/năm mới chạy MỘT lượt.
       3. Rời ô (bấm ra ngoài, chọn xong trên lịch) thì chạy ngay, không bắt đợi.

     Và nhớ giá trị đã chạy: rời ô sau khi nhịp chờ đã chạy rồi thì KHÔNG chạy lại. */
  var NGAY_DU = /^\d{4}-\d{2}-\d{2}$/;
  function noiONgay(o, dat) {
    if (!o) return;
    var hen = null, xong = o.value;
    var chay = function (ngay) {
      clearTimeout(hen); hen = null;
      if (!NGAY_DU.test(o.value) || o.value === xong) return;
      xong = o.value;
      dat(xong);
    };
    o.addEventListener('change', function () {
      if (!NGAY_DU.test(o.value)) return;          // luật 1
      clearTimeout(hen);
      hen = setTimeout(chay, 500);                 // luật 2
    });
    o.addEventListener('blur', chay);              // luật 3
  }

  var dtLuot = 0;   // đếm lượt gọi của tab Doanh thu, cùng lối với `dsLuot` ở Đối soát
  function tai() {
    /* 🔴 KHÔNG BỎ RƠI LƯỢT GỌI SAU. Trước 1.58.3 chỗ này `if (S.dangTai) return;` — đổi ngày Từ
       rồi đổi tiếp ngày đến trong lúc lượt đầu chưa về là lượt hai bị nuốt im lặng: thanh ngày
       ghi khoảng mới, số vẫn của khoảng cũ. Anh Thắng 23/09/2026: *"chọn ngày nó ko tự ra"*.
       Nay đánh số lượt: lượt nào về trễ thì bỏ, lượt mới nhất luôn được vẽ. */
    var luot = ++dtLuot;
    var k = tinhKy();
    var dai = cach(k.tu, k.den) + 1;
    var truoc = doi(k.tu, -dai);
    S.dangTai = true;
    api('bao-cao?tu=' + truoc + '&den=' + k.den).then(function (r) {
      if (luot !== dtLuot) return;
      S.dangTai = false;
      S.ngay = (r && r.ngay) || [];
      ve();
    }).catch(function (e) {
      if (luot !== dtLuot) return;
      S.dangTai = false;
      bao(esc(String(e.message || e)), 'loi');
    });
  }

  /* Nút Lọc: đọc CẢ HAI ô ngày một lượt rồi chạy. Anh Thắng 23/09/2026: *"chọn ngày nó ko tự
     ra, thêm nút tìm kiếm để nó chạy ngày lọc"*. Ô ngày vẫn tự chạy khi chọn xong (noiONgay),
     nhưng nút là đường chắc chắn: bấm là chạy, kể cả khi giá trị không đổi. */
  function locTay(oTu, oDen, dat) {
    var tu = oTu ? oTu.value : '', den = oDen ? oDen.value : '';
    if (!NGAY_DU.test(tu) || !NGAY_DU.test(den)) {
      bao('Chọn đủ ngày <b>Từ</b> và <b>đến</b> rồi bấm Lọc.', 'loi');
      return false;
    }
    if (tu > den) { var t = tu; tu = den; den = t; }
    dat(tu, den);
    return true;
  }
  function locKhiEnter(o, nut) {
    if (!o || !nut) return;
    o.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); nut.click(); }
    });
  }

  /* ---------------- gộp ---------------- */
  function gop(ds) {
    var t = { r: 0, ck: 0, g: 0, o: 0, q: 0, h: z24(), pay: {}, src: {}, ch: {}, mon: {}, ngay: [] };
    ds.forEach(function (d) {
      var n = { ngay: d.ngay, r: 0, ck: 0, o: 0, q: 0 };
      (d.ch || []).forEach(function (c) {
        if (S.ch !== '*' && c.n !== S.ch) return;
        n.r += c.r || 0; n.ck += c.ck || 0; n.o += c.o || 0; n.q += c.q || 0;
        (c.h || []).forEach(function (v, i) { t.h[i] += v; });
        t.r += c.r || 0; t.ck += c.ck || 0; t.g += c.g || 0; t.o += c.o || 0; t.q += c.q || 0;
        t.ch[c.n] = (t.ch[c.n] || 0) + (c.r || 0);
        (c.p || []).forEach(function (p) { t.pay[p.n] = (t.pay[p.n] || 0) + p.r; });
        (c.s || []).forEach(function (p) { t.src[p.n] = (t.src[p.n] || 0) + p.r; });
        (c.i || []).forEach(function (m) {
          var o = t.mon[m.n] || (t.mon[m.n] = { n: m.n, g: m.g || '', q: 0, r: 0 });
          o.q += m.q || 0; o.r += m.r || 0;
        });
      });
      t.ngay.push(n);
    });
    return t;
  }
  function xepGiam(o) {
    return Object.keys(o).map(function (k) { return { n: k, r: o[k] }; })
      .sort(function (a, b) { return b.r - a.r; });
  }

  /* ---------------- mẹo (tooltip) ---------------- */
  var meo;
  function hienMeo(ev, html) {
    if (!meo) { meo = el('div', 'khh-dt-meo'); document.body.appendChild(meo); }
    meo.innerHTML = html; meo.setAttribute('data-on', '1');
    var r = meo.getBoundingClientRect();
    var x = ev.clientX + 14, y = ev.clientY - r.height - 12;
    if (x + r.width > window.innerWidth - 10) x = ev.clientX - r.width - 14;
    if (y < 8) y = ev.clientY + 18;
    meo.style.left = Math.max(8, x) + 'px'; meo.style.top = y + 'px';
  }
  function anMeo() { if (meo) meo.removeAttribute('data-on'); }
  window.addEventListener('scroll', anMeo, true);

  /* ---------------- biểu đồ cột ---------------- */
  function dinhTron(v) {
    if (v <= 0) return 1;
    var raw = v / 4, p = Math.pow(10, Math.floor(Math.log10(raw))), r = raw / p;
    var b = r <= 1 ? 1 : r <= 1.5 ? 1.5 : r <= 2 ? 2 : r <= 2.5 ? 2.5 : r <= 3 ? 3 : r <= 5 ? 5 : 10;
    return b * p * 4;
  }
  function veCot(o, ds, tc) {
    o.textContent = '';
    if (!ds.length) { o.appendChild(el('div', 'trong', tc.trong || 'Chưa có số liệu trong kỳ này.')); return; }
    var max = dinhTron(Math.max.apply(null, ds.map(function (d) { return d.v; })) || 1);
    var dinh = 0; ds.forEach(function (d, i) { if (d.v > ds[dinh].v) dinh = i; });

    var ve = el('div', 've'), trong = el('div', 've-trong');
    for (var g = 0; g <= 4; g++) {
      var k = el('div', 'ke' + (g === 4 ? ' day0' : ''));
      k.style.bottom = (g * 25) + '%';
      k.appendChild(el('span', null, g === 0 ? '0' : tienGon(max * g / 4)));
      trong.appendChild(k);
    }
    var hang = el('div', 'cot-hang');
    ds.forEach(function (d, i) {
      var c = el('div', 'cot' + (i === dinh ? ' dinh' : ''));
      c.tabIndex = 0; c.setAttribute('role', 'img');
      c.setAttribute('aria-label', d.nhan + ': ' + tien(d.v));
      var b = el('i');
      b.style.height = Math.max(0.6, d.v / max * 100) + '%';
      if (tc.mau) b.style.background = tc.mau;
      c.appendChild(b);
      c.addEventListener('pointerenter', function (ev) { c.dataset.on = '1'; hienMeo(ev, tc.meo(d)); });
      c.addEventListener('pointermove', function (ev) { hienMeo(ev, tc.meo(d)); });
      c.addEventListener('pointerleave', function () { c.removeAttribute('data-on'); anMeo(); });
      hang.appendChild(c);
    });
    trong.appendChild(hang); ve.appendChild(trong); o.appendChild(ve);

    var truc = el('div', 'truc');
    var buoc = Math.max(1, Math.ceil(ds.length / (o.clientWidth > 640 ? 14 : 7)));
    ds.forEach(function (d, i) {
      truc.appendChild(el('span', null, (i % buoc === 0 || i === ds.length - 1) ? d.nhanTruc : ''));
    });
    o.appendChild(truc);
    if (tc.ghi) { var g2 = el('div', 'ghi'); g2.innerHTML = tc.ghi(ds[dinh]); o.appendChild(g2); }
  }

  function veHang(o, ds, tong, mau, trong) {
    o.textContent = '';
    if (!ds.length) { o.appendChild(el('div', 'trong', trong || 'File chưa có cột này.')); return; }
    var h = el('div', 'hang');
    ds.slice(0, 8).forEach(function (x, i) {
      var d = el('div', 'd2'), t = el('div', 't'), sw = el('i');
      sw.style.background = mau[i % mau.length];
      t.appendChild(sw); t.appendChild(el('em', null, x.n));
      var s = el('div', 's', tien(x.r));
      s.appendChild(el('small', null, tong ? phan(x.r / tong * 100) : ''));
      var ray = el('div', 'ray'), fi = el('i');
      fi.style.width = (tong ? Math.max(1, x.r / tong * 100) : 0) + '%';
      fi.style.background = mau[i % mau.length];
      ray.appendChild(fi);
      d.appendChild(t); d.appendChild(s); d.appendChild(ray);
      h.appendChild(d);
    });
    o.appendChild(h);
  }

  /* ---------------- vẽ toàn trang ---------------- */
  function q(sel) { return G.querySelector(sel); }

  function ve() {
    var k = tinhKy();
    var trong = S.ngay.filter(function (d) { return d.ngay >= k.tu && d.ngay <= k.den; });
    var dai = cach(k.tu, k.den) + 1;
    var tTruoc = doi(k.tu, -dai), dTruoc = doi(k.tu, -1);
    var truoc = S.ngay.filter(function (d) { return d.ngay >= tTruoc && d.ngay <= dTruoc; });

    var t = gop(trong), tr = gop(truoc);
    var duTruoc = S.cf && S.cf.tu_ngay && S.cf.tu_ngay <= tTruoc && tr.r > 0;

    Array.prototype.forEach.call(G.querySelectorAll('.loc .vien'), function (c) {
      c.setAttribute('aria-pressed', c.dataset.k === S.ky ? 'true' : 'false');
    });
    q('#dtTu').value = k.tu; q('#dtDen').value = k.den;

    /* thẻ số */
    var o = q('#dtThe'); o.textContent = '';
    var to = el('section', 'the to');
    to.appendChild(el('div', 'lb', 'Tổng doanh thu'));
    to.appendChild(el('div', 'v', tien(t.r)));
    var p = el('div', 'p');
    if (duTruoc) {
      var c = (t.r - tr.r) / tr.r * 100;
      p.appendChild(el('span', 'lech ' + (c > 0.5 ? 'len' : c < -0.5 ? 'xuong' : 'ngang'),
        (c > 0 ? '▲ +' : c < 0 ? '▼ ' : '– ') + phan(Math.abs(c))));
      p.appendChild(document.createTextNode(' so với ' + dai + ' ngày liền trước (' + tien(tr.r) + ')'));
    } else {
      p.textContent = ngayVN(k.tu) + ' → ' + ngayVN(k.den) + ' · ' + trong.length + ' ngày có bán';
    }
    to.appendChild(p); o.appendChild(to);

    function the(l, v, s) {
      var e = el('section', 'the'); e.appendChild(el('div', 'lb', l)); e.appendChild(el('div', 'v', v));
      if (s) e.appendChild(el('div', 'p', s)); return e;
    }
    o.appendChild(the('Số hoá đơn', t.o ? nguyen(t.o) : '—',
      trong.length && t.o ? nguyen(Math.round(t.o / trong.length)) + ' hoá đơn/ngày' : ''));
    o.appendChild(the('Trung bình mỗi hoá đơn', t.o ? tien(t.r / t.o) : '—',
      t.ck ? 'Chiết khấu ' + tien(t.ck) : ''));
    var tot = t.ngay.slice().sort(function (a, b) { return b.r - a.r; })[0];
    o.appendChild(the('Ngày bán tốt nhất', tot ? tien(tot.r) : '—', tot ? ngayDu(tot.ngay) : ''));

    /* theo ngày */
    q('#dtGoiNgay').textContent = trong.length
      ? (trong.length + ' ngày · trung bình ' + tien(t.r / trong.length) + '/ngày') : '';
    veCot(q('#dtVeNgay'), t.ngay.map(function (d) {
      return { v: d.r, nhan: ngayDu(d.ngay), nhanTruc: nhanNgay(d.ngay), d: d };
    }), {
      meo: function (d) {
        return '<div class="td">' + esc(d.nhan) + '</div>Doanh thu <b>' + tien(d.v) + '</b>' +
          (d.d.o ? '<br>Hoá đơn <b>' + nguyen(d.d.o) + '</b><br>TB/hoá đơn <b>' + tien(d.v / d.d.o) + '</b>' : '');
      },
      ghi: function (x) { return 'Cao nhất: <b>' + esc(x.nhan) + '</b> — ' + tien(x.v); }
    });

    q('#dtBangNgay').innerHTML = '<table><thead><tr><th>Ngày</th><th>Doanh thu</th><th>Hoá đơn</th>' +
      '<th>TB/hoá đơn</th><th>Chiết khấu</th></tr></thead><tbody>' +
      t.ngay.slice().reverse().map(function (d) {
        return '<tr><td>' + esc(ngayDu(d.ngay)) + '</td><td class="s">' + tien(d.r) + '</td><td class="s">' +
          (d.o ? nguyen(d.o) : '—') + '</td><td class="s">' + (d.o ? tien(d.r / d.o) : '—') +
          '</td><td class="s">' + tien(d.ck) + '</td></tr>';
      }).join('') + '</tbody></table>';

    /* khung giờ */
    var coGio = t.h.some(function (v, i) { return v > 0 && i > 0; });
    if (coGio) {
      var lo = 24, hi = 0;
      t.h.forEach(function (v, i) { if (v > 0) { if (i < lo) lo = i; if (i > hi) hi = i; } });
      lo = Math.max(0, lo - 1); hi = Math.min(23, hi + 1);
      var dsg = [];
      for (var h = lo; h <= hi; h++) dsg.push({ v: t.h[h], nhan: h + ' giờ – ' + (h + 1) + ' giờ', nhanTruc: h + 'h', h: h });
      var dinh = dsg.slice().sort(function (a, b) { return b.v - a.v; })[0];
      q('#dtGoiGio').textContent = dinh ? ('Cao điểm ' + dinh.h + 'h–' + (dinh.h + 1) + 'h') : '';
      veCot(q('#dtVeGio'), dsg, {
        mau: 'var(--s2)',
        meo: function (d) {
          return '<div class="td">' + esc(d.nhan) + '</div>Doanh thu <b>' + tien(d.v) +
            '</b><br>Chiếm <b>' + phan(t.r ? d.v / t.r * 100 : 0) + '</b> cả kỳ';
        }
      });
    } else {
      q('#dtGoiGio').textContent = '';
      q('#dtVeGio').textContent = '';
      q('#dtVeGio').appendChild(el('div', 'trong', 'File nạp vào chưa có cột Giờ.'));
    }

    var pay = xepGiam(t.pay), src = xepGiam(t.src), chs = xepGiam(t.ch);
    veHang(q('#dtPTTT'), pay, cong(pay, function (x) { return x.r; }),
      ['var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)', 'var(--s5)', 'var(--s6)']);
    veHang(q('#dtNguon'), src, cong(src, function (x) { return x.r; }),
      ['var(--s3)', 'var(--s4)', 'var(--s5)'], 'File nạp vào chưa có cột Nguồn.');
    q('#dtCH').parentNode.hidden = chs.length < 2;   // chỉ ẩn khung cửa hàng, không ẩn cả hàng
    q('#dtGoiCH').textContent = chs.length ? chs.length + ' cửa hàng' : '';
    veHang(q('#dtCH'), chs, cong(chs, function (x) { return x.r; }),
      ['var(--s1)', 'var(--s3)', 'var(--s2)', 'var(--s4)', 'var(--s5)', 'var(--s6)']);

    /* món */
    var mon = Object.keys(t.mon).map(function (x) { return t.mon[x]; });
    mon.sort(function (a, b) { return S.xepMon === 'q' ? b.q - a.q : b.r - a.r; });
    q('#dtXepR').setAttribute('aria-pressed', S.xepMon === 'r' ? 'true' : 'false');
    q('#dtXepQ').setAttribute('aria-pressed', S.xepMon === 'q' ? 'true' : 'false');
    var bm = q('#dtBangMon');
    if (!mon.length) {
      bm.textContent = ''; bm.appendChild(el('div', 'trong', 'Chưa có số liệu món trong kỳ này.'));
    } else {
      var top = mon.slice(0, 15);
      var mx = Math.max.apply(null, top.map(function (x) { return S.xepMon === 'q' ? x.q : x.r; }));
      var tongMon = cong(mon, function (x) { return x.r; });
      bm.innerHTML = '<table><thead><tr><th>Món</th><th>Nhóm</th><th>Số lượng</th><th>Doanh thu</th>' +
        '<th>Tỷ trọng</th></tr></thead><tbody>' + top.map(function (x, i) {
          var w = mx ? ((S.xepMon === 'q' ? x.q : x.r) / mx * 100) : 0;
          return '<tr><td><span class="hang-so">' + (i + 1) + '</span>' + esc(x.n) +
            '<span class="sd" style="width:' + w.toFixed(1) + '%"></span></td><td>' + esc(x.g || '—') +
            '</td><td class="s">' + nguyen(x.q) + '</td><td class="s">' + tien(x.r) +
            '</td><td class="s">' + phan(tongMon ? x.r / tongMon * 100 : 0) + '</td></tr>';
        }).join('') + '</tbody></table>';
    }

    var cf = S.cf || {};
    var ng = cf.nguon ? (cf.nguon + (cf.nap_luc ? ' · nạp ' + cf.nap_luc.slice(0, 16) : '')) : 'chưa nạp dữ liệu';
    q('#dtNguonTop').textContent = ng;
    q('#dtChan').textContent = 'Nguồn: ' + ng + (cf.ky ? ' · ' + cf.ky : '');
  }

  /* ---------------- hộp nạp ---------------- */
  var nen, bao_o;
  /* ⚠️ `t` LÀ HTML — hàm này KHÔNG rào chữ. Câu báo do chính mã này dựng nên có <b> cho dễ đọc;
     mọi mẩu chữ đến từ máy chủ, từ tên file hay từ lời lỗi đều phải qua `esc()` TRƯỚC khi ghép
     vào. Quên một chỗ là mở một đường chèn mã vào trang. */
  function bao(t, loai) {
    if (!bao_o) return;
    bao_o.hidden = false;
    bao_o.className = 'khh-dt-bao' + (loai ? ' ' + loai : '');
    bao_o.innerHTML = t;
  }
  /* Mẫu nút dùng ở nhiều câu nhắc — một bản để bốn chỗ không lệch chữ nhau. */
  var NUT_NAP_MOMO_SK = '<button class="nut" type="button" data-mo-nap="momo_sk">⬆️ Nạp sao kê MoMo</button>';

  /* Mở hộp nạp, chọn sẵn một thẻ nếu nơi gọi nói rõ.
     Vì sao cần: mọi câu nhắc trong app đều dạng "bấm Nạp báo cáo → thẻ Sao kê MoMo", tức bắt
     người đọc tự tìm nút ở góc trên rồi đếm sang thẻ thứ tư. Anh Thắng 17/09/2026 đứng ngay
     trước bảng đối soát MoMo và kết luận "chưa có chỗ nạp momo" — chỗ nạp CÓ từ bản 1.28.0,
     chỉ là không ai chỉ đường tới nó. Nhắc mà không kèm đường đi thì bằng không nhắc.
     ⚠️ Chọn thẻ bằng cách BẤM đúng cái thẻ ấy, không tự đặt S.napLoai: luật đổi thẻ (đổi
     S.napLoai + tô nút + ẩn/hiện 4 khối hướng dẫn) nằm trong bộ xử lý bấm. Tự đặt là có bản
     thứ hai của luật, và bản thứ hai sớm muộn lệch — hộp sẽ mở ra thẻ này mà hướng dẫn của
     thẻ khác. */
  function moHop(loai) {
    if (!nen) dungHop();
    bao_o.hidden = true;
    nen.querySelector('.day').textContent = '';
    nen.setAttribute('data-on', '1');
    if (loai) {
      var t = nen.querySelector('[data-loai="' + loai + '"]');
      if (t) t.click();
    }
  }
  function dongHop() { if (nen) nen.removeAttribute('data-on'); }

  function dungHop() {
    nen = el('div', 'khh-dt-nen');
    nen.innerHTML =
      '<div class="khh-dt-hop" role="dialog" aria-modal="true">' +
        '<div class="h"><h2>Nạp số liệu</h2>' +
          '<button class="nut" type="button" data-dong>Đóng</button></div>' +
        '<div class="b">' +
          '<div class="tab-hang nho-hon">' +
            '<button class="tab on" type="button" data-loai="pos">Báo cáo bán hàng FABi</button>' +
            '<button class="tab" type="button" data-loai="sao_ke">Sao kê ngân hàng</button>' +
            '<button class="tab" type="button" data-loai="momo_pos">Giao dịch MoMo (FABi)</button>' +
            '<button class="tab" type="button" data-loai="momo_sk">Sao kê MoMo</button>' +
            '<button class="tab" type="button" data-loai="bes">Bán hàng (Bes)</button>' +
          '</div>' +
          '<div id="dtHdPos">' +
            '<ol><li>Trong CMS FABi: <b>Báo cáo → Báo cáo bán hàng</b> → chọn kỳ → <b>Xuất Excel</b>.</li>' +
            '<li>Thả file xuống ô dưới — file mấy chục MB vẫn được, trang tự cắt nhỏ gửi lên. Máy chủ đọc trang <b>"Tất cả cửa hàng"</b> và bỏ các dòng "Tổng" cộng dồn.</li>' +
            '<li>Nạp lại cùng một ngày thì <b>ghi đè</b> ngày đó, không cộng dồn.</li></ol></div>' +
          '<div id="dtHdMomo" hidden>' +
            '<ol><li>Trong CMS FABi: <b>Báo cáo → MoMo payments</b> → chọn kỳ → <b>Xuất Excel</b>. ' +
            'Mỗi trang tính là một cửa hàng, có cả khối QR Tĩnh và QR Động.</li>' +
            '<li>File này nói <b>giao dịch nào thuộc cửa hàng nào</b> — nên máy FABi dời sang cơ sở ' +
            'khác thì cứ nạp lại file, hệ tự rà, không phải khai tay bảng nào.</li>' +
            '<li>Nạp xong hệ còn <b>học</b> luôn tên cửa hàng bên MoMo ứng với quán nào, từ những ' +
            'cặp giao dịch khớp mã.</li></ol></div>' +
          '<div id="dtHdMomoSk" hidden>' +
            '<ol><li>Trong trang quản lý MoMo: <b>Giao dịch → Xuất báo cáo</b> → file ' +
            '<code>Transaction_report_….csv</code>. MoMo không cho nối API nên đường vào là tải file.</li>' +
            '<li>Đây là <b>tiền MoMo thật sự trả</b>. Đem so với phần MoMo máy POS ghi nhận sẽ ra ' +
            'đúng chỗ hai bên lệch nhau.</li>' +
            '<li>Ghép theo <b>Mã giao dịch</b> — chính là Mã đối tác bên FABi — nên không phụ thuộc ' +
            'tên quán. Nạp lại cùng kỳ thì ghi đè, không cộng dồn.</li>' +
            '<li>Sổ MoMo thường chỉ có mấy quán dùng mã MoMo riêng; những quán ngoài sổ em để ' +
            'riêng một nhóm, <b>không</b> kể là lệch.</li>' +
            '<li><b>Gõ mã tài khoản quyết toán</b> của bản sao kê này (ví dụ <code>KH785</code>). ' +
            'K&amp;H có hai pháp nhân MoMo, mỗi bên một tài khoản và một mức phí — hệ ghi nhớ mấy ' +
            'mã cửa hàng trong file thuộc tài khoản nào, để sau này chia phí cho đúng.</li></ol>' +
            '<label class="o" for="dtTkMomo" style="margin:6px 0 10px">Tài khoản quyết toán' +
            '<input type="text" id="dtTkMomo" placeholder="KH785" style="flex:1"></label>' +
          '</div>' +
          '<div id="dtHdSk" hidden>' +
            '<ol><li>Tải sao kê tài khoản nhận tiền nộp của các cơ sở — bản <b>bảng</b> (.xlsx hoặc .csv), không phải PDF.</li>' +
            '<li>Em <b>chỉ lấy tiền vào</b>, bỏ mọi khoản chi. Nạp lại cùng một kỳ không cộng dồn (khoá theo mã giao dịch).</li>' +
            '<li>Nhận mặt cơ sở theo nội dung chuyển khoản hoặc số tài khoản — khai ở <b>Quản trị → Sao kê ngân hàng</b>.</li>' +
            '<li>Tiền nộp sáng hôm sau tính cho doanh thu <b>hôm trước</b> (giờ cắt khai được).</li></ol></div>' +
          '<div id="dtHdBes" hidden>' +
            '<ol><li>Dành cho cửa hàng chạy hệ <b>Bes</b> (không phải FABi): trong BesReportViewer ' +
            'xuất <b>TỔNG HỢP MÓN ĂN BÁN</b> ra <code>.csv</code>, một ngày một file.</li>' +
            '<li>Ngày lấy từ <b>chân trang</b> của báo cáo, nên đừng cắt dòng ấy đi. Nạp lại cùng ' +
            'ngày thì ghi đè, không cộng dồn.</li>' +
            '<li><b>Gõ tên cơ sở</b> vào ô dưới. Tên trong file thường trơ ("FUNZONE") và sẽ đụng ' +
            'mấy cơ sở đã có — gõ tên đầy đủ, dùng đúng một tên ấy cho mọi lần nạp về sau.</li>' +
            '<li>⚠️ Báo cáo này <b>không có hình thức thanh toán</b>, nên cơ sở này có doanh thu ' +
            'nhưng <b>chưa đối soát được</b> tiền mặt / CK / MoMo. Phần ấy nhập tay ở ' +
            '<b>Nhập báo cáo ngày</b>, hoặc xin Bes một báo cáo có cột thanh toán rồi bảo em.</li></ol>' +
            /* Lớp `.o` là lớp nhãn+ô nhập sẵn có của trang (doanh-thu.css dòng 57-58), đã
               có kiểu cho `input` bên trong. Đặt lớp mới là thêm một chỗ phải nhớ sửa khi
               đổi bộ áo — và `kiem-bo-ao-tron.php` canh đúng chuyện ấy. */
            '<label class="o" for="dtCoSo" style="margin:6px 0 10px">Tên cơ sở ghi vào sổ' +
            '<input type="text" id="dtCoSo" placeholder="ví dụ: FUNZONE KVC Aeon Bình Dương" ' +
            'style="flex:1"></label>' +
          '</div>' +
          '<div class="khh-dt-tha" id="dtTha" tabindex="0" role="button" aria-label="Chọn hoặc thả file">' +
            '<strong>Thả file vào đây</strong><span>hoặc bấm để chọn — nhận .xlsx, .csv</span>' +
            '<input type="file" id="dtFile" accept=".xlsx,.xlsm,.csv,.tsv,.txt" hidden></div>' +
          '<div class="khh-dt-bao" id="dtBao" hidden></div>' +
        '</div>' +
        '<div class="f"><span class="day" id="dtTrangThai"></span>' +
          '<button class="nut" type="button" id="dtKiemTra">Kiểm tra máy chủ</button></div>' +
      '</div>';
    document.body.appendChild(nen);
    bao_o = nen.querySelector('#dtBao');

    nen.addEventListener('click', function (e) {
      if (e.target === nen || e.target.hasAttribute('data-dong')) dongHop();
    });
    nen.querySelector('#dtKiemTra').addEventListener('click', function () {
      bao('Đang kiểm tra…');
      api('kiem-tra').then(function (k) {
        var thieu = [];
        if (!k.ziparchive) thieu.push('thiếu ZipArchive');
        if (!k.xmlreader) thieu.push('thiếu XMLReader');
        if (!k.ghi_duoc_tam) thieu.push('không ghi được thư mục tạm ' + k.thu_muc_tam);
        if (!k.bang) thieu.push('chưa tạo được bảng dữ liệu');
        bao(esc('PHP ' + k.php + ' · WordPress ' + k.wp + ' · plugin ' + k.plugin +
          ' · bộ nhớ ' + k.bo_nho + ' · chạy tối đa ' + k.thoi_gian + 's' +
          ' · tải lên ' + k.tai_len + ' (post_max ' + k.post_max + ')' +
          (thieu.length ? ' — VƯỚNG: ' + thieu.join(', ') + '.' : ' — đủ điều kiện đọc file .xlsx.')),
          thieu.length ? 'loi' : 'xong');
      }).catch(function (e) { bao(esc(String(e.message || e)), 'loi'); });
    });

    Array.prototype.forEach.call(nen.querySelectorAll('[data-loai]'), function (b) {
      b.addEventListener('click', function () {
        S.napLoai = b.dataset.loai;
        Array.prototype.forEach.call(nen.querySelectorAll('[data-loai]'), function (x) {
          x.classList.toggle('on', x === b);
        });
        nen.querySelector('#dtHdPos').hidden = S.napLoai !== 'pos';
        nen.querySelector('#dtHdSk').hidden = S.napLoai !== 'sao_ke';
        nen.querySelector('#dtHdMomo').hidden = S.napLoai !== 'momo_pos';
        nen.querySelector('#dtHdMomoSk').hidden = S.napLoai !== 'momo_sk';
        nen.querySelector('#dtHdBes').hidden = S.napLoai !== 'bes';
        bao_o.hidden = true;
      });
    });
    S.napLoai = 'pos';

    var tha = nen.querySelector('#dtTha'), file = nen.querySelector('#dtFile');
    tha.addEventListener('click', function () { file.click(); });
    tha.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); file.click(); }
    });
    ['dragenter', 'dragover'].forEach(function (t) {
      tha.addEventListener(t, function (e) { e.preventDefault(); tha.dataset.over = '1'; });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      tha.addEventListener(t, function (e) { e.preventDefault(); tha.removeAttribute('data-over'); });
    });
    tha.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) guiFile(e.dataTransfer.files[0]); });
    file.addEventListener('change', function () { if (file.files[0]) guiFile(file.files[0]); });
  }

  /* Gửi file theo từng mẩu 1MB.
     Bản xuất một tuần của FABi nặng vài chục MB, hosting thường chặn tải lên ở
     2–32MB nên gửi nguyên file là máy chủ cắt ngang và trả về trang HTML. */
  var MAU = 1048576;
  function guiFile(f) {
    bao_o.hidden = true;
    var trangThai = nen.querySelector('#dtTrangThai');
    var duoi = (f.name.split('.').pop() || '').toLowerCase();
    if (['xlsx', 'xlsm', 'csv', 'tsv', 'txt'].indexOf(duoi) < 0) {
      bao('Chỉ nhận .xlsx, .csv hoặc .tsv. File .xls đời cũ thì anh mở ra lưu lại thành .xlsx giúp em.', 'loi');
      return;
    }
    var khoa = (Date.now().toString(36) + Math.random().toString(36).slice(2, 10)).replace(/[^a-z0-9]/g, '');
    var tong = Math.max(1, Math.ceil(f.size / MAU));
    var i = 0;

    function gui() {
      var fd = new FormData();
      fd.append('khoa', khoa);
      fd.append('phan', String(i));
      fd.append('tong', String(tong));
      fd.append('ten', f.name);
      fd.append('loai', S.napLoai || 'pos');
      /* Ô tên cơ sở chỉ có ở thẻ Bes. Gửi kèm MỌI mẩu: máy chủ đọc file ở mẩu CUỐI, mà mỗi mẩu
         là một lượt POST riêng — gửi mỗi mẩu đầu là mẩu cuối không có tên, rồi rơi về tên trơ
         trong file và số liệu vào sai cơ sở. */
      var o_cs = nen && nen.querySelector('#dtCoSo');
      if (o_cs && o_cs.value.trim()) { fd.append('co_so', o_cs.value.trim()); }
      /* Cùng lý do như ô tên cơ sở: gửi kèm MỌI mẩu, vì máy chủ đọc file ở mẩu CUỐI. */
      var o_tk = nen && nen.querySelector('#dtTkMomo');
      if (o_tk && o_tk.value.trim()) { fd.append('tai_khoan', o_tk.value.trim()); }
      fd.append('mau', f.slice(i * MAU, (i + 1) * MAU), 'mau.bin');
      trangThai.textContent = (i === tong - 1)
        ? 'Đã gửi xong, máy chủ đang đọc file…'
        : 'Đang gửi ' + f.name + ' — ' + Math.round(i / tong * 100) + '% (' + (i + 1) + '/' + tong + ' mẩu)';
      api('nap-mau', { method: 'POST', body: fd }).then(function (r) {
        i++;
        if (!r.xong && i < tong) { gui(); return; }
        trangThai.textContent = '';
        if (r.loai === 'bes') {
          bao('Đã nạp <b>' + tien(r.thanh_tien) + '</b> cho cơ sở <b>' + esc(r.cua_hang) + '</b> ' +
            'ngày ' + ngayVN(r.ngay) + ' — ' + nguyen(r.so_mon) + ' món, ' + nguyen(r.so_ve) + ' vé.' +
            '<br><b>Chưa có hình thức thanh toán</b> (báo cáo Bes không có cột ấy), nên cơ sở này ' +
            'chưa đối soát được tiền mặt / CK / MoMo.', 'tot');
          khoiDong(true);
          return;
        }
        if (r.loai === 'momo_sk') {
          var quan = Object.keys(r.quan || {});
          bao('Đã nạp <b>' + nguyen(r.da_ghi) + ' giao dịch</b> từ sao kê MoMo' +
            (quan.length ? ' — ' + quan.length + ' cửa hàng: ' + esc(quan.map(function (m) {
              return (r.quan[m] || m); }).join(' · ')) : '') +
            (r.bo_qua ? ' (bỏ ' + nguyen(r.bo_qua) + ' dòng không đọc được ngày)' : '') + '. ' +
            (r.hoc ? 'Đã học <b>' + nguyen(r.hoc) + '</b> mã cửa hàng MoMo ứng với quán nào. ' : '') +
            ((r.lan_can || []).length
              ? '⚠️ ' + r.lan_can.length + ' mã cửa hàng MoMo trỏ về HAI quán trong kỳ này — đúng ' +
                'cảnh máy vừa dời cơ sở. Hệ không đoán, giao dịch khớp mã vẫn gán đúng theo file FABi.'
              : ''), 'xong');
          khoiDong(true);
          return;
        }
        if (r.loai === 'momo_pos') {
          bao('Đã nạp <b>' + nguyen(r.da_ghi) + ' giao dịch MoMo</b> từ ' + nguyen(r.so_trang) +
            ' cửa hàng' + (r.bo_qua ? ' (bỏ ' + nguyen(r.bo_qua) + ' dòng không đọc được ngày)' : '') + '. ' +
            (r.hoc ? 'Đã học <b>' + nguyen(r.hoc) + '</b> tên cửa hàng bên MoMo ứng với quán nào. ' : '') +
            ((r.lan_can || []).length
              ? '⚠️ ' + r.lan_can.length + ' tên bên MoMo trỏ về HAI quán khác nhau trong kỳ này — ' +
                'đúng cảnh máy vừa dời cơ sở. Hệ không đoán, giao dịch vẫn gán đúng theo file.'
              : ''), 'xong');
          khoiDong(true);
          return;
        }
        if (r.loai === 'sao_ke') {
          var cg = r.chua_gan || { so_dong: 0, so_tien: 0 };
          bao('Đã nạp <b>' + nguyen(r.da_ghi) + ' khoản tiền vào</b> từ ' + nguyen(r.so_dong) + ' dòng sao kê' +
            (r.tien_ra ? ' (bỏ ' + nguyen(r.tien_ra) + ' dòng tiền ra)' : '') + '. ' +
            (cg.so_dong
              ? '<b>Còn ' + nguyen(cg.so_dong) + ' khoản chưa nhận ra cơ sở</b> — tổng ' + tien(cg.so_tien) +
                '. Vào Quản trị → Sao kê ngân hàng để khai nội dung nhận mặt.'
              : 'Mọi khoản đều nhận ra cơ sở.') +
            (r.doan_dau
              ? ' ⚠️ File không có cột tiền vào/ra nên em coi mọi số dương là tiền vào — nếu sai anh tải bản có cột Ghi nợ / Ghi có.'
              : ''), cg.so_dong ? 'loi' : 'xong');
        } else {
          bao('Đã nạp ' + nguyen(r.so_dong) + ' dòng' +
            (r.bo_qua ? ' (bỏ ' + nguyen(r.bo_qua) + ' dòng "Tổng" cộng dồn của FABi)' : '') +
            ' · ' + nguyen(r.so_ban_ghi) + ' ngày×cửa hàng · tổng ' + tien(r.tong_doanh_thu) + '.', 'xong');
          S.ky = 'all';
        }
        khoiDong(true);
      }).catch(function (e) {
        trangThai.textContent = '';
        bao(esc(String(e.message || e)), 'loi');
      });
    }
    gui();
  }

  /* ---------------- dựng khung ---------------- */
  function khung() {
    return '' +
      '<div class="top">' +
        '<h1>Doanh thu FABi</h1><span class="nguon" id="dtNguonTop"></span>' +
        '<span class="ai" id="dtAi" hidden></span>' +
        '<button class="nut" type="button" id="dtIn">In / Lưu PDF</button>' +
        '<button class="nut chinh" type="button" id="dtNap">Nạp báo cáo</button>' +
      '</div>' +
      '<div class="than">' +
        '<div class="tab-hang">' +
          '<button class="tab on" type="button" data-tab="doanhthu">Doanh thu</button>' +
          '<button class="tab" type="button" data-tab="nhap">Nhập báo cáo ngày</button>' +
          '<button class="tab" type="button" data-tab="kho">Kho hàng hoá</button>' +
          '<button class="tab" type="button" data-tab="doisoat">Đối soát</button>' +
          '<button class="tab" type="button" data-tab="quantri" id="dtTabQT" hidden>Quản trị</button>' +
        '</div>' +
        '<div id="dtTabNhap" hidden></div>' +
        '<div id="dtTabKho" hidden></div>' +
        '<div id="dtTabDoiSoat" hidden></div>' +
        '<div id="dtTabQuanTri" hidden></div>' +
        '<div id="dtTabDoanhThu">' +
        '<div class="loc">' +
          '<button class="vien" data-k="1" type="button">Ngày mới nhất</button>' +
          '<button class="vien" data-k="7" type="button" aria-pressed="true">7 ngày</button>' +
          '<button class="vien" data-k="30" type="button">30 ngày</button>' +
          '<button class="vien" data-k="thang" type="button">Tháng này</button>' +
          '<button class="vien" data-k="all" type="button">Tất cả</button>' +
          '<span class="day"></span>' +
          '<span class="o" id="dtOCH"><label for="dtChonCH">Cửa hàng</label>' +
            '<select id="dtChonCH"><option value="*">Tất cả cửa hàng</option></select></span>' +
          '<span class="o"><label for="dtTu">Từ</label><input type="date" id="dtTu">' +
            '<label for="dtDen">đến</label><input type="date" id="dtDen">' +
            '<button class="nut" type="button" id="dtLoc" title="Chạy theo hai ngày đã chọn">Lọc</button></span>' +
        '</div>' +
        '<div id="dtTrong" class="khung" hidden><div class="trong">' +
          '<b>Kho chưa có số liệu.</b><br>Bấm <b>Nạp báo cáo</b> ở trên rồi thả file xuất từ FABi vào.' +
        '</div></div>' +
        '<div id="dtNoiDung">' +
          '<section class="the-hang" id="dtThe"></section>' +
          '<section class="khung"><header><h2>Doanh thu theo ngày</h2>' +
            '<span class="goi" id="dtGoiNgay"></span></header>' +
            '<div id="dtVeNgay"></div>' +
            '<details><summary>Xem bảng số liệu từng ngày</summary>' +
              '<div class="bang-cuon" id="dtBangNgay"></div></details></section>' +
          '<div class="doi">' +
            '<section class="khung"><header><h2>Doanh thu theo khung giờ</h2>' +
              '<span class="goi" id="dtGoiGio"></span></header><div id="dtVeGio"></div></section>' +
            '<section class="khung"><header><h2>Hình thức thanh toán</h2></header>' +
              '<div id="dtPTTT"></div></section>' +
          '</div>' +
          '<div class="doi">' +
            '<section class="khung"><header><h2>Doanh thu theo cửa hàng</h2>' +
              '<span class="goi" id="dtGoiCH"></span></header><div id="dtCH"></div></section>' +
            '<section class="khung"><header><h2>Tại chỗ / mang về</h2></header>' +
              '<div id="dtNguon"></div></section>' +
          '</div>' +
          '<section class="khung"><header><h2>Món bán chạy</h2><span class="goi">' +
            '<button class="vien" type="button" id="dtXepR" aria-pressed="true">Theo doanh thu</button> ' +
            '<button class="vien" type="button" id="dtXepQ">Theo số lượng</button></span></header>' +
            '<div class="bang-cuon" id="dtBangMon"></div></section>' +
        '</div>' +
        '<div class="chan"><span id="dtChan"></span><span class="day"></span>' +
          '<button class="nut" type="button" id="dtXoa" hidden>Xoá kho số liệu</button></div>' +
        '</div>' +
      '</div>';
  }

  function noiSuKien() {
    Array.prototype.forEach.call(G.querySelectorAll('.loc .vien'), function (c) {
      c.addEventListener('click', function () { S.ky = c.dataset.k; tai(); });
    });
    noiONgay(q('#dtTu'), function (v) { S.ky = 'tay'; S.tu = v; tai(); });
    noiONgay(q('#dtDen'), function (v) { S.ky = 'tay'; S.den = v; tai(); });
    q('#dtLoc').addEventListener('click', function () {
      locTay(q('#dtTu'), q('#dtDen'), function (tu, den) { S.ky = 'tay'; S.tu = tu; S.den = den; tai(); });
    });
    locKhiEnter(q('#dtTu'), q('#dtLoc'));
    locKhiEnter(q('#dtDen'), q('#dtLoc'));
    q('#dtChonCH').addEventListener('change', function () { S.ch = q('#dtChonCH').value; ve(); });
    q('#dtXepR').addEventListener('click', function () { S.xepMon = 'r'; ve(); });
    q('#dtXepQ').addEventListener('click', function () { S.xepMon = 'q'; ve(); });
    q('#dtIn').addEventListener('click', function () { window.print(); });
    /* ⚠️ PHẢI bọc. Truyền `moHop` trực tiếp làm bộ xử lý thì tham số đầu là ĐỐI TƯỢNG SỰ KIỆN,
       nên từ lúc moHop nhận tham số `loai`, nút này sẽ đi tìm thẻ `[data-loai="[object
       PointerEvent]"]` — không thấy, im lặng không chọn thẻ nào. Trước đây moHop không nhận
       tham số nên viết gọn thế vô hại; nay thì không. */
    q('#dtNap').addEventListener('click', function () { moHop(); });
    /* Nút "nạp file ngay tại đây" nằm TRONG các khối được vẽ lại (đối soát, tổng hợp…), nên gắn
       sự kiện lên từng nút là gắn xong rồi mất khi vẽ lại. Uỷ quyền một lần trên khung. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-mo-nap]') : null;
      if (!b) return;
      e.preventDefault();
      moHop(b.getAttribute('data-mo-nap'));
    });
    /* Bấm số trang. Uỷ quyền một lần, dùng cho MỌI bảng có phân trang.
       ⚠️ Đổi trang KHÔNG gọi lại máy chủ — số liệu đã có sẵn trong S, chỉ vẽ lại. Gọi lại là
          mỗi lần bấm Sau lại một lượt tải, và trên 3G ngoài cửa hàng thì nó giật. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-trang]') : null;
      if (!b || b.disabled) return;
      e.preventDefault();
      S.trang = S.trang || {};
      S.trang[b.getAttribute('data-trang')] = parseInt(b.getAttribute('data-so'), 10) || 1;
      ve();
    });
    /* Sửa một lượt phí: đổ lại vào chính ô nhập ở trên rồi bấm Lưu phí là ghi đè.
       Làm được là nhờ 1.40.0 — gõ lại ĐÚNG khoảng cũ nay tính là sửa, không bị chối là chồng
       ngày nữa (`khh_dt_momo_phi_dat` bỏ chính nó ra khỏi phép dò chồng, rồi INSERT ... ON
       DUPLICATE KEY UPDATE trên khoá `(tu,den,tai_khoan)`). Trước đó muốn chữa một con số gõ
       sai thì chỉ còn cách Xoá rồi nhập lại — anh Thắng gõ nhầm "68.866" thành 69đ và mắc
       đúng chỗ này. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-phi-sua]') : null;
      if (!b) return;
      e.preventDefault();
      var dat = function (n, v) {
        var o = G.querySelector('[data-phi="' + n + '"]');
        if (o) { o.value = v; }
        return o;
      };
      dat('tk', b.getAttribute('data-phi-tk'));
      dat('tu', b.getAttribute('data-phi-tu'));
      dat('den', b.getAttribute('data-phi-den'));
      var oSo = dat('so', b.getAttribute('data-phi-so'));
      /* Bôi sẵn số cũ: gõ số mới là đè thẳng, không phải xoá từng chữ. Và cuộn ô nhập vào tầm
         mắt — bảng đã nhập nằm DƯỚI ô nhập, nên bấm Sửa ở dòng cuối mà không cuộn thì màn hình
         y như không có gì xảy ra. */
      if (oSo) {
        if (oSo.scrollIntoView) { oSo.scrollIntoView({ block: 'center' }); }
        oSo.focus();
        if (oSo.select) { oSo.select(); }
      }
      var nut = G.querySelector('[data-phi-luu]');
      if (nut) { nut.textContent = 'Lưu đè'; }
    });
    /* Xoá một lượt phí đã nhập. Hỏi lại một câu: đây là con số tiền. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-phi-xoa]') : null;
      if (!b) return;
      e.preventDefault();
      if (!window.confirm('Xoá lượt phí này?')) return;
      /* 🔴 `id` ĐI TRONG ĐƯỜNG DẪN, KHÔNG PHẢI TRONG THÂN.
         Anh Thắng 18/09/2026: *"bấm xóa mà không xóa được"* — và không câu báo nào. PHP chỉ tự
         đọc thân multipart/form-data cho phương thức POST; với DELETE thì `$_POST` rỗng, mà
         WP_REST_Request cũng không bóc multipart (nó chỉ bóc JSON và form-urlencoded). Nên
         `id` tới máy chủ là RỖNG -> xoá hàng số 0 -> không hàng nào -> trả về `xong:false`,
         mã 200, màn hình vẽ lại y như cũ. Tham số trên đường dẫn thì phương thức nào cũng đọc
         được. */
      api('momo-phi?id=' + encodeURIComponent(b.getAttribute('data-phi-xoa')), { method: 'DELETE' })
        .then(function (j) {
          /* Và PHẢI xem máy chủ có xoá thật không. Trước đây chỗ này bỏ qua hẳn kết quả, nên
             một lượt xoá hụt trông y hệt một lượt xoá được. */
          if (!j || !j.xong) {
            throw new Error('Máy chủ không xoá được lượt phí này. Anh tải lại trang rồi thử lại; ' +
              'còn báo lỗi thì chụp màn hình gửi em.');
          }
          return taiDoiSoat();
        })
        .catch(function (err) { window.alert(err.message || err); });
    });
    /* Lưu bảng ghép cơ sở -> tài khoản. Gửi CẢ BẢNG một lượt, kể cả ô để trống (ô trống là
       lệnh bỏ ghép) — gửi từng ô một thì bỏ ghép không có cách nào diễn đạt. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-ghep-luu]') : null;
      if (!b) return;
      e.preventDefault();
      var map = {};
      Array.prototype.forEach.call(G.querySelectorAll('[data-ghep]'), function (o) {
        map[o.getAttribute('data-ghep')] = o.value.trim();
      });
      var fd = new FormData();
      fd.append('ghep', JSON.stringify(map));
      b.disabled = true; b.textContent = 'Đang lưu…';
      /* 🔴 `taiDoiSoat()`, KHÔNG phải `tai()` — bảng ghép và phí đều nằm trong `S.dsR`. */
      api('momo-tk-ghep', { method: 'POST', body: fd }).then(taiDoiSoat).catch(function (err) {
        b.disabled = false; b.textContent = 'Lưu ghép';
        window.alert(err.message || err);
      });
    });
    /* Lưu phí MoMo. Cũng uỷ quyền, vì khối này nằm trong phần được vẽ lại mỗi lần đổi kỳ. */
    G.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-phi-luu]') : null;
      if (!b) return;
      e.preventDefault();
      var hop = b.parentNode;
      var lay = function (n) { var o = hop.querySelector('[data-phi="' + n + '"]'); return o ? o.value.trim() : ''; };
      if (!lay('tk')) { window.alert('Gõ mã tài khoản trước (ví dụ KH785).'); return; }
      if (!lay('so')) { window.alert('Gõ số phí — lấy ở ô "Số tiền điều chỉnh" trên màn Đối soát của MoMo.'); return; }
      var fd = new FormData();
      fd.append('tai_khoan', lay('tk'));
      fd.append('tu', lay('tu')); fd.append('den', lay('den')); fd.append('phi', lay('so'));
      b.disabled = true; b.textContent = 'Đang lưu…';
      /* 🔴 GỌI `taiDoiSoat()`, KHÔNG PHẢI `tai()`. Phí nằm trong `S.dsR` — bộ số của màn Đối
         soát — còn `tai()` chỉ nạp lại `S.ngay` rồi vẽ lại bằng `S.dsR` CŨ. Nên lưu xong màn
         đứng im, phải F5 mới thấy: đúng cái anh Thắng gặp. Và `tai()` còn tự thoát khi đang có
         một lượt tải khác chạy, nên có lúc nó chẳng làm gì cả. */
      api('momo-phi', { method: 'POST', body: fd }).then(function () {
        taiDoiSoat();
      }).catch(function (err) {
        b.disabled = false; b.textContent = 'Lưu phí';
        /* Câu chối của máy chủ (chồng ngày, ngày sai…) PHẢI hiện ra — nuốt nó đi là người dùng
           bấm hoài mà không hiểu vì sao không lưu được. */
        window.alert(err.message || err);
      });
    });
    q('#dtXoa').addEventListener('click', function () {
      if (!window.confirm('Xoá toàn bộ số liệu trong kho? Số trong FABi và file gốc không bị ảnh hưởng.')) return;
      api('xoa', { method: 'POST' }).then(function () { khoiDong(true); });
    });
    Array.prototype.forEach.call(G.querySelectorAll('.tab-hang .tab'), function (b) {
      b.addEventListener('click', function () { doiTab(b.dataset.tab); });
    });
    var h; window.addEventListener('resize', function () { clearTimeout(h); h = setTimeout(function () {
      if (S.tab === 'doanhthu') ve();
    }, 200); });
  }

  function khoiDong(lai) {
    api('cau-hinh').then(function (cf) {
      S.cf = cf;
      if (cf.can_dang_nhap) { manPin(''); return; }
      if (!q('#dtChonCH')) { G.innerHTML = khung(); noiSuKien(); }
      var sel = q('#dtChonCH'), cu = S.ch;
      sel.innerHTML = '<option value="*">Tất cả cửa hàng</option>';
      (cf.cua_hang || []).forEach(function (t) {
        var o = document.createElement('option'); o.value = t; o.textContent = t; sel.appendChild(o);
      });
      if ((cf.cua_hang || []).indexOf(cu) < 0) S.ch = '*';
      sel.value = S.ch;
      q('#dtOCH').hidden = (cf.cua_hang || []).length < 2;

      var rong = !cf.so_ban_ghi;
      q('#dtTrong').hidden = !rong;
      q('#dtNoiDung').hidden = rong;
      q('#dtNap').hidden = !cf.duoc_nap;
      q('#dtXoa').hidden = rong || !cf.duoc_nap;
      q('#dtTabQT').hidden = !cf.quan_tri;
      veNguoiXem(cf);
      if (cf.chua_ghep_co_so) {
        /* Đã đẩy sang nhưng mã cơ sở chưa khai trong bảng ghép -> người này không thấy gì. Nói
           thẳng ra, đừng để họ ngồi nhìn màn trống rồi tưởng hệ hỏng. */
        q('#dtTrong').hidden = false;
        q('#dtNoiDung').hidden = true;
        q('#dtTrong').innerHTML = '<div class="trong"><b>Chưa ghép cơ sở cho tài khoản này.</b><br>' +
          'Mã cơ sở bên nhân sự chưa được khai sang tên cơ sở trên máy POS, nên màn chưa biết lấy ' +
          'số của quán nào. Nhờ quản trị vào tab <b>Quản trị → Ghép cơ sở</b> khai một lần.</div>';
        q('#dtNguonTop').textContent = '';
        return;
      }
      if (rong) { q('#dtNguonTop').textContent = 'kho trống'; q('#dtChan').textContent = ''; return; }
      if (!lai && S.ky === '7' && cf.tu_ngay === cf.den_ngay) S.ky = 'all';
      tai();
    }).catch(function (e) {
      G.innerHTML = '<div class="khh-dt-tai">Không đọc được kho số liệu: ' + esc(e.message || e) + '</div>';
    });
  }

  /* ================= cửa PIN ================= */
  /* Cửa hàng trưởng không có tài khoản WordPress — họ vào bằng PIN chấm công, đúng con số đang gõ
     hằng ngày ở máy chấm công và ở app chi phí. */
  function manPin(loi) {
    G.innerHTML =
      '<div class="khh-dt-pin"><form class="hop-pin" id="dtFormPin">' +
        '<h1>Báo cáo doanh thu</h1>' +
        '<p>Nhập PIN chấm công của anh/chị để vào màn báo cáo cơ sở.</p>' +
        '<input type="password" inputmode="numeric" autocomplete="off" pattern="[0-9]*" ' +
          'maxlength="8" id="dtPin" placeholder="PIN 4–8 số" aria-label="PIN">' +
        '<button class="nut chinh" type="submit" id="dtVao">Vào</button>' +
        '<div class="loi-pin" id="dtLoiPin">' + (loi ? esc(loi) : '') + '</div>' +
        '<div class="chu-them">Quên PIN thì nhờ quản lý cấp lại ở màn Nhân sự. ' +
          'Người của văn phòng thì <a href="' + esc((S.cf && S.cf.link_wp) || '') + '">đăng nhập bằng tài khoản</a>.</div>' +
      '</form></div>';
    var oi = q('#dtPin');
    if (oi) oi.focus();
    q('#dtFormPin').addEventListener('submit', function (ev) {
      ev.preventDefault();
      var pin = q('#dtPin').value.trim();
      var nut = q('#dtVao');
      nut.disabled = true; nut.textContent = 'Đang kiểm…';
      var fd = new FormData(); fd.append('pin', pin);
      api('dang-nhap', { method: 'POST', body: fd }).then(function (r) {
        datThe(r.token);
        G.innerHTML = khung();
        noiSuKien();
        khoiDong();
      }).catch(function (e) {
        nut.disabled = false; nut.textContent = 'Vào';
        q('#dtLoiPin').textContent = e.message || e;
        q('#dtPin').value = ''; q('#dtPin').focus();
      });
    });
  }

  function thoatPin() {
    var cf = S.cf || {};
    var t = q('#dtThoat');
    if (t) { t.disabled = true; t.textContent = 'Đang thoát…'; }
    api('dang-xuat', { method: 'POST' }).then(function (r) {
      datThe(''); S.cf = null;
      /* Vừa huỷ phiên WordPress thì nonce REST đang cầm là của phiên đã chết: gọi tiếp
         `cau-hinh` sẽ bị chối 403. Tải lại trang để nhận nonce mới — và tải lại ĐÚNG địa chỉ đang
         đứng, không phụ thuộc link đẹp đã flush hay chưa. */
      if (r && r.wp) { window.location.reload(); return; }
      khoiDong();
    }).catch(function () {
      datThe(''); S.cf = null;
      /* REST hỏng (plugin bảo mật chặn) mà đang là tài khoản WordPress thì lùi về đường thoát
         cũ của WordPress — còn hơn kẹt lại là người trước trên máy dùng chung. */
      if (!cf.bang_pin && cf.link_ra) { window.location.href = cf.link_ra; return; }
      khoiDong();
    });
  }

  function veNguoiXem(cf) {
    var o = q('#dtAi');
    if (!o) return;
    if (!cf.ten_toi) { o.hidden = true; return; }
    o.hidden = false;
    /* THOÁT PHẢI CÓ CHO CẢ HAI LỐI VÀO.
       Trước 18/09/2026 nút này chỉ hiện khi `bang_pin` — tức chỉ cửa hàng trưởng vào bằng PIN mới
       thoát được. Người văn phòng vào bằng tài khoản WordPress thì không có đường nào ra, và đây
       là trang mở trên máy dùng chung ở cửa hàng: không thoát được nghĩa là người sau ngồi vào
       vẫn đang là người trước, xem được đúng những gì người trước xem.

       Hai lối thoát KHÁC NHAU, không dùng chung một đường:
         · vào bằng PIN  -> REST `dang-xuat`: đóng phiên PIN, KHÔNG đụng đăng nhập WordPress.
         · vào bằng tài khoản -> `cf.link_ra` (wp_logout_url): thoát hẳn khỏi WordPress.
       ⚠️ Lối thứ hai phải là <a> cho người BẤM, vì wp_logout_url mang nonce — gọi bằng fetch là
          WordPress chối, và chối im lặng nên người dùng chỉ thấy nút bấm không phản hồi. */
    /* 23/09/2026 anh Thắng: "đăng xuất ra nó nhảy ra trang wordpress". Lối <a> wp_logout_url đưa
       người ta sang wp-login.php và không quay lại. Nay CẢ HAI lối bấm cùng một nút: REST
       `dang-xuat` huỷ phiên WordPress lẫn phiên PIN ngay ở máy chủ, rồi màn tải lại đúng địa chỉ
       đang đứng -> gặp ô PIN. `link_ra` chỉ còn là đường LÙI khi REST hỏng (plugin bảo mật chặn). */
    o.innerHTML = '<span class="ten">' + esc(cf.ten_toi) + '</span>' +
      '<button class="vien" type="button" id="dtThoat">Thoát</button>';
    var t = q('#dtThoat');
    if (t) t.addEventListener('click', thoatPin);
  }

  function mount(goc) {
    G = goc;
    G.classList.add('khh-dt');
    G.innerHTML = khung();
    noiSuKien();
    khoiDong();
    return G;
  }

  /* ================= tab ================= */
  function doiTab(t) {
    S.tab = t;
    Array.prototype.forEach.call(G.querySelectorAll('.tab-hang .tab'), function (b) {
      b.classList.toggle('on', b.dataset.tab === t);
    });
    q('#dtTabDoanhThu').hidden = t !== 'doanhthu';
    q('#dtTabNhap').hidden = t !== 'nhap';
    q('#dtTabKho').hidden = t !== 'kho';
    q('#dtTabDoiSoat').hidden = t !== 'doisoat';
    q('#dtTabQuanTri').hidden = t !== 'quantri';
    if (t === 'nhap') dungNhap();
    if (t === 'kho') taiKho();
    if (t === 'doisoat') taiDoiSoat();
    if (t === 'quantri') taiQuanTri();
  }

  /* ================= tab NHẬP BÁO CÁO NGÀY ================= */
  function homQua() {
    var d = new Date(); d.setDate(d.getDate() - 1); return ymd(d);
  }

  /* Ngày hôm nay, lấy theo múi giờ của MÁY NGƯỜI DÙNG qua `ymd()` — không dùng
     `toISOString()`, hàm ấy đổi sang UTC nên buổi tối ở Việt Nam sẽ ra ngày hôm trước. */
  function homNay() { return ymd(new Date()); }

  function dungNhap() {
    var o = q('#dtTabNhap');
    if (o.dataset.xong) { napBaoCao(); return; }
    o.dataset.xong = '1';
    var ds = (S.cf && S.cf.cua_hang) || [];
    o.innerHTML =
      '<div class="khung"><header><h2>Nhập báo cáo ngày</h2>' +
        '<span class="goi">Máy POS tự điền phần của nó — cơ sở chỉ nhập phần máy không biết.</span></header>' +
        /* Quy trình hằng ngày (anh Thắng 24/09/2026: "làm quy trình báo cáo hằng ngày tự động — báo cáo cơ
           sở thôi"): trên cùng là VIỆC CÒN TREO của đúng cơ sở mình, bấm là nhảy tới ngày ấy; dưới ô chọn
           ngày là BỐN BƯỚC của ngày đang mở. Hệ theo dõi và nhắc — không tự điền số thay cơ sở. */
        '<div id="bcViec" class="bc-viec"></div>' +
        '<div class="loc" style="margin:14px 0 4px">' +
          '<span class="o"><label for="bcNgay">Ngày</label><input type="date" id="bcNgay"></span>' +
          '<span class="o" id="bcOCH"><label for="bcCH">Cơ sở</label><select id="bcCH">' +
            ds.map(function (c) { return '<option value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('') +
          '</select></span>' +
        '</div>' +
        '<div id="bcBuoc" class="bc-buoc"></div>' +
        '<div id="bcPos" class="bc-pos"></div>' +
        '<div id="bcHang"></div>' +
        '<div class="bc-luoi" id="bcNhap">' +
          o_nhap('tien_mat_dem', 'Tiền mặt đếm trong két', 'cuối ca, đếm thật') +
          o_nhap('tien_nop', 'Tiền thực nộp về quỹ', 'số tiền bàn giao') +
          o_nhap('so_bill_huy', 'Số bill đã huỷ', 'đếm số bill') +
          o_nhap('tien_bill_huy', 'Tiền của bill đã huỷ', '') +
          o_nhap('tong_chuyen', 'Tổng lượt chạy / lượt phục vụ', '') +
          o_nhap('tong_khach', 'Tổng khách vào', 'đếm tại cửa') +
          o_nhap('ve_giay', 'Vé giấy đã soát', 'nếu có soát vé') +
        '</div>' +
        '<label class="bc-o" style="margin-top:12px"><b>Ghi chú</b>' +
          '<textarea id="bc_ghi_chu" rows="2" placeholder="Sự cố, lý do huỷ bill, khách đoàn…"></textarea></label>' +
        '<div id="bcLech" class="bc-lech"></div>' +
        '<div class="bc-nut">' +
          '<button class="nut chinh" type="button" id="bcLuu">Lưu báo cáo</button>' +
          '<button class="nut" type="button" id="bcChot">Lưu và chốt ngày</button>' +
          /* Anh Thắng 24/09/2026: "Lưu và chốt xong nó sẽ có thêm tải ảnh và chia sẻ báo cáo này lên Zalo".
             Hai nút chỉ hiện khi ngày này ĐÃ có báo cáo lưu (ảnh dựng từ số đã lưu, không từ ô đang gõ). */
          '<button class="vien" type="button" id="bcTaiAnh" hidden>Tải ảnh báo cáo</button>' +
          '<button class="vien" type="button" id="bcChiaSe" hidden>Chia sẻ lên Zalo</button>' +
          '<span class="day"></span><span id="bcTrangThai"></span>' +
        '</div>' +
        '<div class="khh-dt-bao" id="bcBao" hidden></div>' +
      '</div>';

    q('#bcNgay').value = S.nhapNgay || homQua();
    var sel = q('#bcCH');
    if (S.nhapCH) sel.value = S.nhapCH;
    q('#bcNgay').addEventListener('change', napBaoCao);
    sel.addEventListener('change', napBaoCao);
    Array.prototype.forEach.call(o.querySelectorAll('.bc-luoi input'), function (i) {
      i.addEventListener('input', tinhLech);
    });
    q('#bcLuu').addEventListener('click', function () { luuBaoCao(0); });
    q('#bcChot').addEventListener('click', function () { luuBaoCao(1); });
    q('#bcTaiAnh').addEventListener('click', taiAnhBC);
    q('#bcChiaSe').addEventListener('click', chiaSeBC);
    q('#bcViec').addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('[data-viec-ngay]') : null;
      if (!b) return;
      ev.preventDefault();
      q('#bcNgay').value = b.getAttribute('data-viec-ngay');
      var c = b.getAttribute('data-viec-ch');
      if (c && q('#bcCH').querySelector('option[value="' + c.replace(/"/g, '\\"') + '"]')) q('#bcCH').value = c;
      napBaoCao();
    });
    taiViec();
    napBaoCao();
  }

  /* ---- VIỆC CÒN TREO của cơ sở mình (máy chủ lọc theo người xem) ---- */
  function taiViec() {
    var o = q('#bcViec');
    if (!o) return;
    api('quy-trinh').then(function (r) { veViec(o, r); }).catch(function () { o.innerHTML = ''; });
  }

  var QT_NHAN = { chua_fabi: 'chưa có số máy POS', chua_nop: 'chưa nộp', da_luu: 'đã lưu, chưa chốt', da_chot: 'đã chốt' };

  function veViec(o, r) {
    var ds = (r && r.viec) || [], han = (r && r.han) || '10:00';
    if (!ds.length) {
      o.innerHTML = '<div class="bc-viec-o xong">✓ <b>Đã chốt hết</b> tới hôm qua. Ngày mới chốt trước <b>' + esc(han) + '</b> sáng hôm sau.</div>';
      return;
    }
    var quaHan = ds.filter(function (x) { return x.qua_han; }).length;
    o.innerHTML = '<div class="bc-viec-o' + (quaHan ? ' xau' : '') + '"><b>' + ds.length + ' ngày chưa chốt</b>' +
      (quaHan ? ' · <b>' + quaHan + ' quá hạn</b>' : '') + ' — chốt trước ' + esc(han) + ' sáng hôm sau. Bấm để mở:</div>' +
      '<div class="viec">' + ds.map(function (x) {
        return '<button class="vien' + (x.qua_han ? ' qua-han' : '') + '" type="button" data-viec-ngay="' + esc(x.ngay) + '" data-viec-ch="' + esc(x.cua_hang) + '">' +
          esc(ngayVN(x.ngay)) + (r.viec.some(function (y) { return y.cua_hang !== x.cua_hang; }) ? ' · ' + esc(String(x.cua_hang).slice(0, 28)) : '') +
          ' <span>' + esc(QT_NHAN[x.trang_thai] || x.trang_thai) + (x.qua_han ? ' · quá hạn' : '') + '</span></button>';
      }).join('') + '</div>';
  }

  /* ---- BỐN BƯỚC của ngày đang mở: số máy về → cơ sở khai → sổ kho → chốt ---- */
  function veBuoc(t) {
    var o = q('#bcBuoc');
    if (!o) return;
    if (!t || !t.buoc) { o.innerHTML = ''; return; }
    var b = t.buoc;
    var buoc = [
      ['Số máy POS về', b.fabi, b.fabi ? '' : 'FABi chưa gửi / hộp thư chưa lấy — nhờ văn phòng nạp'],
      ['Cơ sở khai', b.khai, b.khai ? '' : 'soát hàng bán, đếm két, khách vào rồi Lưu'],
      ['Sổ kho', b.kho, b.kho === null ? '' : (b.kho ? '' : 'nhập / huỷ / tồn còn ở tab Kho hàng hoá')],
      ['Chốt ngày', b.chot, b.chot ? '' : 'bấm Lưu và chốt ngày trước ' + String(t.han || '').slice(11)],
    ];
    o.innerHTML = '<div class="buoc' + (t.qua_han ? ' xau' : '') + '">' + buoc.map(function (x, i) {
      var lop = x[1] === true ? 'xong' : (x[1] === null ? 'khong' : 'chua');
      return '<span class="' + lop + '" title="' + esc(x[2]) + '"><i>' + (x[1] === true ? '✓' : (i + 1)) + '</i>' + esc(x[0]) + '</span>';
    }).join('') +
      (t.qua_han ? '<b class="han">quá hạn ' + esc(String(t.han || '').slice(11)) + '</b>' : (b.chot ? '' : '<b class="han">hạn ' + esc(String(t.han || '').slice(11)) + ' ' + esc(ngayVN(String(t.han || '').slice(0, 10))) + '</b>')) +
      '</div>';
  }

  function o_nhap(id, nhan, goi) {
    return '<label class="bc-o"><b>' + esc(nhan) + '</b>' +
      '<input type="text" inputmode="numeric" id="bc_' + id + '" placeholder="0">' +
      (goi ? '<span class="goi">' + esc(goi) + '</span>' : '') + '</label>';
  }

  function soNhap(id) {
    var v = (q('#bc_' + id).value || '').replace(/[^\d\-]/g, '');
    return v ? parseInt(v, 10) : 0;
  }

  function napBaoCao() {
    var ngay = q('#bcNgay').value, ch = q('#bcCH').value;
    S.nhapNgay = ngay; S.nhapCH = ch;
    if (!ngay || !ch) return;
    q('#bcTrangThai').textContent = 'đang tải…';
    api('bao-cao-ngay?ngay=' + encodeURIComponent(ngay) + '&cua_hang=' + encodeURIComponent(ch))
      .then(function (r) {
        S.pos = r.pos;
        var p = r.pos;
        q('#bcPos').innerHTML = p
          ? '<div class="bc-pos-hang">' +
              o_pos('Doanh thu máy POS', tien(p.doanh_thu)) +
              /* Hai ô sổ kế toán: sale vé / bán lẻ (anh Thắng 23/09/2026). */
              o_pos('Sale vé (POS)', tien(p.tien_ve || 0)) +
              o_pos('Sale bán lẻ (POS)', tien(p.tien_le || 0)) +
              o_pos('Sale phụ (POS)', tien(p.tien_phu || 0)) +
              (S.cf && S.cf.quan_tri
                ? '<div class="bc-pos-o"><span class="lb">Cách tách</span><b><a href="#" id="bcSangCauHinh" style="font-weight:600">Quản trị → Sale vé / Bán lẻ / Sale phụ</a></b></div>'
                : '') +
              o_pos('Số hoá đơn', nguyen(p.so_hd)) +
              o_pos('Số vé bán', p.so_ve ? nguyen(p.so_ve) : '—') +
              /* Khách theo máy = vé × số khách mỗi vé (combo Trẻ em + Người lớn = 2). Anh Thắng
                 23/09/2026. null = cơ sở này chưa bóc tách vé -> bày "—", không bày 0. */
              o_pos('Khách vào (POS)' + (p.khach_tam ? ' · tạm tính' : ''), p.khach_may != null ? nguyen(p.khach_may) : '—') +
              o_pos('Tiền mặt (POS)', tien(p.tien_mat)) +
              o_pos('Chuyển khoản (POS)', tien(p.ck)) +
            '</div>' +
            ((p.ve_chua_tach || []).length
              ? '<div class="chu-them" id="bcVeChuaTach">Vé <b>chưa bóc tách</b>, đang <b>tạm tính 1 khách mỗi vé</b> (' + nguyen(p.khach_tam || 0) + ' khách): ' +
                p.ve_chua_tach.map(esc).join(', ') + ' — combo 2 người thì phải khai 2, ' +
                (S.cf && S.cf.duoc_nap ? 'khai ở <b>Quản trị → Bóc tách vé → khách</b>.' : 'nhờ văn phòng khai ở tab Quản trị.') + '</div>'
              : '')
          : '<div class="trong">Ngày này chưa có số liệu máy POS trong kho. Nạp file FABi cho ngày đó rồi quay lại.</div>';
        var b = r.bao_cao || {};
        S.bcHienTai = { pos: p, bao_cao: b, ngay: ngay, ch: ch };
        veBuoc(r.quy_trinh);
        /* Có báo cáo đã lưu (có người nhập) thì mới có gì để tải ảnh / chia sẻ. */
        var daLuu = !!(b && b.nguoi);
        q('#bcTaiAnh').hidden = !daLuu;
        q('#bcChiaSe').hidden = !daLuu;
        q('#bcChiaSe').textContent = b && b.chot ? 'Chia sẻ lên Zalo' : 'Chia sẻ (chưa chốt)';
        veHangBan(p, b);
        var sang = q('#bcSangCauHinh');
        if (sang) sang.addEventListener('click', function (ev) {
          ev.preventDefault();
          /* Cấu hình theo cửa hàng: mở Quản trị đúng quán đang nhập, cuộn tới khối. */
          S.cauHinhCS = ch;
          doiTab('quantri');
          setTimeout(function () { var k = q('#dtNhomVe'); if (k) k.scrollIntoView({ behavior: 'smooth' }); }, 900);
        });
        ['tien_mat_dem', 'tien_nop', 'so_bill_huy', 'tien_bill_huy', 'tong_chuyen', 'tong_khach', 've_giay']
          .forEach(function (k) {
            var v = b[k] != null ? Math.round(b[k]) : '';
            q('#bc_' + k).value = v === '' || v === 0 ? '' : nguyen(v);
          });
        q('#bc_ghi_chu').value = b.ghi_chu || '';
        if (r.cua_toi && q('#bcCH').value !== r.cua_toi && !q('#bcCH').dataset.daChon) {
          q('#bcCH').dataset.daChon = '1';
          q('#bcCH').value = r.cua_toi;
          S.nhapCH = r.cua_toi;
          napBaoCao();
          return;
        }
        var khoa = !r.duoc_ghi;
        Array.prototype.forEach.call(q('#dtTabNhap').querySelectorAll('input,textarea,button'), function (e) {
          if (e.id !== 'bcNgay' && e.id !== 'bcCH') e.disabled = khoa;
        });
        q('#bcOCH').hidden = !!r.cua_toi;
        q('#bcTrangThai').textContent = b.nguoi
          ? (b.chot ? 'đã chốt' : 'đã lưu') + ' bởi ' + b.nguoi + (b.sua_luc ? ' · ' + String(b.sua_luc).slice(0, 16) : '')
          : (khoa
              /* Người PIN đẩy sang mà chưa được cấp vai: nói rõ VÌ SAO khoá và AI mở được, chứ
                 không để họ ngồi trước một bảng ô mờ rồi gọi điện hỏi. */
              ? (S.cf && S.cf.bang_pin && !S.cf.vai
                  ? 'chỉ xem — chưa được cấp quyền nhập, nhờ quản trị cấp ở tab Quản trị'
                  : 'chỉ xem')
              : 'chưa nhập');
        tinhLech();
      }).catch(function (e) {
        q('#bcTrangThai').textContent = '';
        baoBC(String(e.message || e), 'loi');
      });
  }

  function o_pos(nhan, giatri) {
    return '<div class="bc-pos-o"><span class="lb">' + esc(nhan) + '</span><b>' + esc(giatri) + '</b></div>';
  }

  function tinhLech() {
    var p = S.pos, o = q('#bcLech');
    if (!p) { o.innerHTML = ''; return; }
    var dem = soNhap('tien_mat_dem'), nop = soNhap('tien_nop');
    var khach = soNhap('tong_khach'), ve = Math.round(p.so_ve || 0);
    /* Có bóc tách vé thì so với KHÁCH theo máy; chưa có thì lùi về số vé như trước. */
    var may = p.khach_may != null ? Math.round(p.khach_may) : ve;
    var nhanMay = p.khach_may != null ? 'Khách đếm ở cửa so với máy (đã bóc tách vé)' : 'Khách vào so với vé bán';
    var h = '';
    function dong(nhan, v, dv, xau) {
      var lop = v === 0 ? 'khop' : (xau ? 'xau' : 'thuong');
      h += '<div class="bc-lech-o ' + lop + '"><span>' + esc(nhan) + '</span><b>' +
        (v > 0 ? '+' : '') + (dv === 'tien' ? tien(v) : nguyen(v)) + '</b></div>';
    }
    if (dem) dong('Đếm két so với tiền mặt POS', dem - Math.round(p.tien_mat), 'tien', Math.abs(dem - p.tien_mat) > 0);
    if (dem && nop) dong('Đếm được nhưng chưa nộp', dem - nop, 'tien', dem - nop > 0);
    if (khach && may) dong(nhanMay, khach - may, 'so', khach - may > 0);
    o.innerHTML = h || '<div class="bc-lech-o khop"><span>Nhập số vào để hệ thống tính lệch ngay</span><b></b></div>';
  }

  function baoBC(t, loai) {
    var m = q('#bcBao'); m.hidden = false;
    m.className = 'khh-dt-bao' + (loai ? ' ' + loai : ''); m.textContent = t;
  }

  /* ---- HÀNG BÁN THEO MÁY — NHÂN VIÊN SOÁT, LỆCH MỚI NHẬP ----
     Anh Thắng 23/09/2026: *"hiện số lượng hàng bán và thành tiền để nhân viên kiểm kho bán được và
     chốt bán thực tế đúng máy POS không, nếu lệch nhân viên mới nhập, đúng rồi thì để nguyên, chốt
     đúng xong thì bấm lưu và chốt, để kế toán xác nhận bạn tại cửa hàng chốt bán đúng như vậy"*.
     Ô "SL thực" để TRỐNG nghĩa là đúng như máy. Chỉ dòng có gõ số khác máy mới được gửi lên. */
  function veHangBan(p, b) {
    var o = q('#bcHang');
    if (!o) return;
    var mon = (p && p.mon) || [];
    if (!mon.length) { o.innerHTML = ''; return; }
    var thuc = (b && b.mon_thuc) || {};
    var tq = 0, tr = 0, lech = 0;
    mon.forEach(function (m) { tq += m.q || 0; tr += m.r || 0; if (thuc[m.n] != null && Math.round(thuc[m.n]) !== Math.round(m.q)) lech++; });
    var h = '<details class="bc-hang" id="bcHangChi"' + (lech || !b || !b.nguoi ? ' open' : '') + '>' +
      '<summary><b>Hàng bán theo máy POS</b> — ' + mon.length + ' món · ' + nguyen(tq) + ' cái · ' + tien(tr) +
        '<span id="bcHangTom" class="chu-them" style="display:inline;margin-left:8px">' +
        (lech ? '<b style="color:var(--xau)">' + lech + ' món cơ sở chốt khác máy</b>' : (b && b.nguoi ? 'cơ sở chốt khớp máy' : '')) + '</span></summary>' +
      '<div class="chu-them" style="margin-top:6px">Soát từng dòng với hàng bán thật. <b>Đúng máy thì để trống</b>; ' +
        'lệch mới gõ <b>số lượng thực bán</b> vào ô. Soát xong bấm <b>Lưu và chốt ngày</b> — kế toán thấy ở tab Đối soát ' +
        'là cơ sở đã xác nhận bán đúng như máy (hay lệch món nào).</div>' +
      '<div class="bang-the bang-cuon" style="margin-top:8px"><table><thead><tr>' +
        '<th>Món</th><th>SL máy</th><th>Thành tiền</th><th>SL thực (nếu lệch)</th><th>Lệch</th></tr></thead><tbody>' +
      mon.map(function (m) {
        var v = thuc[m.n] != null ? Math.round(thuc[m.n]) : '';
        var d = v === '' ? 0 : v - Math.round(m.q);
        return '<tr data-mon="' + esc(m.n) + '" data-may="' + Math.round(m.q) + '">' +
          '<td data-nhan="Món" style="text-align:left">' + esc(m.n) + (m.g || m.l ? '<span style="display:block;color:var(--ink-3);font-size:12px">' + esc(m.g) + (m.l ? ' · ' + esc(m.l) : (m.ve ? ' · vé' : '')) + '</span>' : '') + '</td>' +
          '<td class="s" data-nhan="SL máy">' + nguyen(m.q) + '</td>' +
          '<td class="s" data-nhan="Thành tiền">' + tien(m.r) + '</td>' +
          '<td data-nhan="SL thực"><input type="number" min="0" step="1" inputmode="numeric" data-thuc="' + esc(m.n) + '" value="' + v + '" placeholder="' + nguyen(m.q) + '" style="width:92px"></td>' +
          '<td class="s o-lech-mon" data-nhan="Lệch"' + (d ? ' style="color:var(--xau);font-weight:600"' : '') + '>' + (d ? (d > 0 ? '+' : '') + nguyen(d) : '') + '</td>' +
        '</tr>';
      }).join('') + '</tbody></table></div></details>';
    o.innerHTML = h;
    Array.prototype.forEach.call(o.querySelectorAll('input[data-thuc]'), function (i) {
      i.addEventListener('input', function () {
        var tr_ = i.closest('tr'), may = parseInt(tr_.dataset.may, 10) || 0;
        var v = i.value.trim() === '' ? null : parseInt(i.value, 10);
        var d = v === null || isNaN(v) ? 0 : v - may;
        var c = tr_.querySelector('.o-lech-mon');
        c.textContent = d ? (d > 0 ? '+' : '') + nguyen(d) : '';
        c.style.color = d ? 'var(--xau)' : ''; c.style.fontWeight = d ? '600' : '';
        var n = 0;
        Array.prototype.forEach.call(o.querySelectorAll('input[data-thuc]'), function (j) {
          var m2 = parseInt(j.closest('tr').dataset.may, 10) || 0, w = j.value.trim();
          if (w !== '' && parseInt(w, 10) !== m2) n++;
        });
        q('#bcHangTom').innerHTML = n ? '<b style="color:var(--xau)">' + n + ' món chốt khác máy</b>' : 'đang khớp máy';
      });
    });
  }

  /* Chỉ gửi dòng có gõ số VÀ khác máy: [ tên món => SL thực ]. Trống = đúng máy = không gửi. */
  function docMonThuc() {
    var b = {};
    Array.prototype.forEach.call(document.querySelectorAll('#bcHang input[data-thuc]'), function (i) {
      var w = i.value.trim();
      if (w === '') return;
      var v = parseInt(w, 10), may = parseInt(i.closest('tr').dataset.may, 10) || 0;
      if (isNaN(v) || v < 0 || v === may) return;
      b[i.dataset.thuc] = v;
    });
    return b;
  }

  /* ================= ẢNH BÁO CÁO NGÀY & CHIA SẺ ZALO =================
     Anh Thắng 24/09/2026: *"bổ sung tính năng Lưu và chốt xong nó sẽ có thêm tải ảnh và chia sẻ báo cáo
     này lên Zalo"*. Ảnh vẽ bằng <canvas> từ CHÍNH SỐ ĐÃ LƯU (S.bcHienTai), không chụp màn: chụp màn kéo
     theo nút bấm, ô nhập, cuộn dở; còn Zalo cần một tấm ảnh đọc được trên điện thoại. Không dùng thư
     viện ngoài. Chia sẻ dùng khung chia sẻ của hệ điều hành (Web Share API, có Zalo trong đó); máy tính
     không có khung ấy thì tải ảnh về và chép tóm tắt vào bộ nhớ tạm để dán vào Zalo web. */
  function dongBC() {
    var h = S.bcHienTai || {};
    var p = h.pos || {}, b = h.bao_cao || {};
    var mon = p.mon || [], thuc = b.mon_thuc || {};
    var so = function (v) { return v == null || v === '' ? 0 : Math.round(Number(v)); };
    var khach = so(b.tong_khach), dem = so(b.tien_mat_dem), nop = so(b.tien_nop);
    var may = p.khach_may != null ? Math.round(p.khach_may) : Math.round(p.so_ve || 0);
    var lech = [];
    if (dem) lech.push(['Đếm két − tiền mặt POS', (dem - Math.round(p.tien_mat || 0)), 'tien']);
    if (dem && nop) lech.push(['Đếm được − đã nộp', dem - nop, 'tien']);
    if (khach && may) lech.push([p.khach_may != null ? 'Khách đếm − khách máy' : 'Khách đếm − vé bán', khach - may, 'so']);
    var lechMon = mon.filter(function (m) { return thuc[m.n] != null && Math.round(thuc[m.n]) !== Math.round(m.q); });
    return { p: p, b: b, mon: mon, thuc: thuc, lech: lech, lechMon: lechMon, so: so, ngay: h.ngay || '', ch: h.ch || '' };
  }

  function tomTatBC() {
    var d = dongBC(), p = d.p, b = d.b;
    var dong = [
      'BÁO CÁO NGÀY ' + ngayVN(d.ngay) + ' — ' + d.ch,
      'Doanh thu máy POS: ' + tien(p.doanh_thu) + ' · ' + nguyen(p.so_hd) + ' hoá đơn',
      'Sale vé ' + tien(p.tien_ve || 0) + ' · Bán lẻ ' + tien(p.tien_le || 0) + ' · Sale phụ ' + tien(p.tien_phu || 0),
      'Tiền mặt POS ' + tien(p.tien_mat) + ' · Chuyển khoản ' + tien(p.ck),
      'Đếm két ' + tien(d.so(b.tien_mat_dem)) + ' · Nộp quỹ ' + tien(d.so(b.tien_nop)),
      'Khách vào đếm ' + nguyen(d.so(b.tong_khach)) + (p.khach_may != null ? ' · máy ' + nguyen(p.khach_may) : ''),
      'Bill huỷ ' + nguyen(d.so(b.so_bill_huy)) + ' · ' + tien(d.so(b.tien_bill_huy)),
      'Hàng bán: ' + (d.lechMon.length ? d.lechMon.length + ' món lệch máy' : 'khớp máy'),
    ];
    d.lech.forEach(function (l) { dong.push(l[0] + ': ' + (l[1] > 0 ? '+' : '') + (l[2] === 'tien' ? tien(l[1]) : nguyen(l[1]))); });
    if (b.ghi_chu) dong.push('Ghi chú: ' + b.ghi_chu);
    dong.push((b.chot ? 'ĐÃ CHỐT' : 'đã lưu, chưa chốt') + (b.nguoi ? ' · ' + b.nguoi : '') + (b.sua_luc ? ' · ' + String(b.sua_luc).slice(0, 16) : ''));
    return dong.join('\n');
  }

  /* Vẽ ảnh. Trả về <canvas>. Bề ngang 900px, tỉ lệ 2 cho nét trên điện thoại. */
  function veAnhBC() {
    var d = dongBC(), p = d.p, b = d.b;
    var W = 900, TL = 2, y = 0;
    var dongMon = d.mon.slice(0, 40);
    var H = 470 + d.lech.length * 30 + dongMon.length * 30 + (b.ghi_chu ? 60 : 0) + 70;
    var c = document.createElement('canvas');
    c.width = W * TL; c.height = H * TL;
    var g = c.getContext('2d');
    g.scale(TL, TL);
    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
    var chu = function (t, x, yy, opt) {
      opt = opt || {};
      g.font = (opt.dam ? '700 ' : '400 ') + (opt.co || 15) + 'px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
      g.fillStyle = opt.mau || '#111827';
      g.textAlign = opt.phai ? 'right' : 'left';
      g.fillText(String(t), x, yy);
    };
    var ke = function (yy) { g.strokeStyle = '#e5e7eb'; g.lineWidth = 1; g.beginPath(); g.moveTo(24, yy); g.lineTo(W - 24, yy); g.stroke(); };
    /* Đầu */
    g.fillStyle = '#1e3a8a'; g.fillRect(0, 0, W, 78);
    chu('BÁO CÁO NGÀY ' + ngayVN(d.ngay), 24, 34, { dam: true, co: 22, mau: '#fff' });
    chu(d.ch, 24, 60, { co: 14, mau: '#dbeafe' });
    chu(b.chot ? 'ĐÃ CHỐT' : 'CHƯA CHỐT', W - 24, 34, { dam: true, co: 16, mau: b.chot ? '#86efac' : '#fde68a', phai: true });
    chu((b.nguoi ? b.nguoi : '') + (b.sua_luc ? ' · ' + String(b.sua_luc).slice(0, 16) : ''), W - 24, 60, { co: 13, mau: '#dbeafe', phai: true });
    y = 110;
    /* Ô máy POS: 4 cột × 2 hàng */
    var oMay = [
      ['Doanh thu máy POS', tien(p.doanh_thu)], ['Sale vé', tien(p.tien_ve || 0)], ['Sale bán lẻ', tien(p.tien_le || 0)], ['Sale phụ', tien(p.tien_phu || 0)],
      ['Số hoá đơn', nguyen(p.so_hd)], ['Khách vào (POS)' + (p.khach_tam ? ' · tạm' : ''), p.khach_may != null ? nguyen(p.khach_may) : '—'], ['Tiền mặt (POS)', tien(p.tien_mat)], ['Chuyển khoản (POS)', tien(p.ck)],
    ];
    chu('MÁY POS', 24, y, { dam: true, co: 12, mau: '#6b7280' }); y += 10;
    oMay.forEach(function (o, i) {
      var cx = 24 + (i % 4) * ((W - 48) / 4), cy = y + Math.floor(i / 4) * 56;
      chu(o[0], cx, cy + 16, { co: 11, mau: '#6b7280' });
      chu(o[1], cx, cy + 40, { dam: true, co: 17 });
    });
    y += 2 * 56 + 8; ke(y); y += 26;
    /* Cơ sở khai */
    var oKhai = [
      ['Tiền mặt đếm két', tien(d.so(b.tien_mat_dem))], ['Thực nộp về quỹ', tien(d.so(b.tien_nop))], ['Bill huỷ', nguyen(d.so(b.so_bill_huy)) + ' · ' + tien(d.so(b.tien_bill_huy))], ['Lượt chạy', nguyen(d.so(b.tong_chuyen))],
      ['Khách vào (đếm ở cửa)', nguyen(d.so(b.tong_khach))], ['Vé giấy đã soát', nguyen(d.so(b.ve_giay))],
    ];
    chu('CƠ SỞ KHAI', 24, y, { dam: true, co: 12, mau: '#6b7280' }); y += 10;
    oKhai.forEach(function (o, i) {
      var cx = 24 + (i % 4) * ((W - 48) / 4), cy = y + Math.floor(i / 4) * 56;
      chu(o[0], cx, cy + 16, { co: 11, mau: '#6b7280' });
      chu(o[1], cx, cy + 40, { dam: true, co: 17 });
    });
    y += 2 * 56 + 8; ke(y); y += 26;
    /* Lệch */
    chu('LỆCH', 24, y, { dam: true, co: 12, mau: '#6b7280' }); y += 22;
    if (!d.lech.length) { chu('Chưa có số để so.', 24, y, { co: 14, mau: '#6b7280' }); y += 30; }
    d.lech.forEach(function (l) {
      var v = l[1], txt = (v > 0 ? '+' : '') + (l[2] === 'tien' ? tien(v) : nguyen(v));
      chu(l[0], 24, y, { co: 14 });
      chu(txt, W - 24, y, { dam: true, co: 15, mau: v === 0 ? '#15803d' : '#b91c1c', phai: true });
      y += 30;
    });
    ke(y); y += 26;
    /* Hàng bán */
    chu('HÀNG BÁN THEO MÁY' + (d.lechMon.length ? ' — ' + d.lechMon.length + ' món cơ sở chốt khác máy' : ' — cơ sở chốt khớp máy'), 24, y, { dam: true, co: 12, mau: '#6b7280' }); y += 22;
    chu('Món', 24, y, { co: 11, mau: '#6b7280' }); chu('SL máy', 560, y, { co: 11, mau: '#6b7280', phai: true });
    chu('SL thực', 680, y, { co: 11, mau: '#6b7280', phai: true }); chu('Thành tiền', W - 24, y, { co: 11, mau: '#6b7280', phai: true }); y += 8;
    dongMon.forEach(function (m) {
      y += 30;
      var t = d.thuc[m.n], lechM = t != null && Math.round(t) !== Math.round(m.q);
      var ten = String(m.n); if (ten.length > 46) ten = ten.slice(0, 45) + '…';
      chu(ten, 24, y, { co: 14 });
      chu(nguyen(m.q), 560, y, { co: 14, phai: true });
      chu(t != null ? nguyen(t) : '=', 680, y, { co: 14, dam: lechM, mau: lechM ? '#b91c1c' : '#6b7280', phai: true });
      chu(tien(m.r), W - 24, y, { co: 14, phai: true });
    });
    if (d.mon.length > dongMon.length) { y += 26; chu('… và ' + (d.mon.length - dongMon.length) + ' món nữa', 24, y, { co: 12, mau: '#6b7280' }); }
    y += 20;
    if (b.ghi_chu) { ke(y); y += 26; chu('Ghi chú: ' + String(b.ghi_chu).slice(0, 110), 24, y, { co: 13 }); y += 14; }
    ke(y + 6);
    chu('Doanh thu FABi · ' + (S.cf && S.cf.ten_toi ? 'in bởi ' + S.cf.ten_toi + ' · ' : '') + new Date().toLocaleString('vi-VN'), 24, H - 18, { co: 11, mau: '#9ca3af' });
    return c;
  }

  function tenTepBC() {
    var d = dongBC();
    return 'bao-cao-' + d.ngay + '-' + String(d.ch).replace(/[^\w\u00C0-\u1EF9]+/g, '-').slice(0, 40) + '.png';
  }

  function taiAnhBC() {
    var c = veAnhBC();
    c.toBlob(function (blob) {
      if (!blob) { window.alert('Không dựng được ảnh trên trình duyệt này.'); return; }
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = tenTepBC();
      document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
      baoBC('Đã tải ảnh ' + a.download + '.', 'xong');
    }, 'image/png');
  }

  function chiaSeBC() {
    var c = veAnhBC(), tom = tomTatBC();
    c.toBlob(function (blob) {
      if (!blob) { window.alert('Không dựng được ảnh trên trình duyệt này.'); return; }
      var tep = new File([blob], tenTepBC(), { type: 'image/png' });
      /* Điện thoại: khung chia sẻ của máy, có Zalo trong đó. */
      if (navigator.share && navigator.canShare && navigator.canShare({ files: [tep] })) {
        navigator.share({ files: [tep], title: 'Báo cáo ngày', text: tom }).catch(function () { /* người dùng đóng khung */ });
        return;
      }
      /* Máy tính: tải ảnh + chép tóm tắt, rồi dán vào Zalo. */
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = tep.name;
      document.body.appendChild(a); a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
      var xong = function () {
        baoBC('Trình duyệt này không có khung chia sẻ. Đã tải ảnh về và chép tóm tắt vào bộ nhớ tạm — mở Zalo, dán (Ctrl+V) rồi kéo ảnh vừa tải vào.', 'xong');
      };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(tom).then(xong, xong); else xong();
    }, 'image/png');
  }

  function luuBaoCao(chot) {
    var fd = new FormData();
    fd.append('ngay', q('#bcNgay').value);
    fd.append('cua_hang', q('#bcCH').value);
    ['tien_mat_dem', 'tien_nop', 'so_bill_huy', 'tien_bill_huy', 'tong_chuyen', 'tong_khach', 've_giay']
      .forEach(function (k) { fd.append(k, String(soNhap(k))); });
    fd.append('ghi_chu', q('#bc_ghi_chu').value);
    fd.append('mon_thuc', JSON.stringify(docMonThuc()));
    if (chot) fd.append('chot', '1');
    q('#bcLuu').disabled = true; q('#bcChot').disabled = true;
    api('bao-cao-ngay', { method: 'POST', body: fd }).then(function (r) {
      q('#bcLuu').disabled = false; q('#bcChot').disabled = false;
      baoBC(chot ? 'Đã lưu và chốt ngày.' : 'Đã lưu báo cáo.' +
        (r.sua_lan ? ' (bản trước được giữ lại trong lịch sử sửa)' : ''), 'xong');
      napBaoCao();
      taiViec();
    }).catch(function (e) {
      q('#bcLuu').disabled = false; q('#bcChot').disabled = false;
      baoBC(String(e.message || e), 'loi');
    });
  }

  /* ================= tab KHO HÀNG HOÁ =================
     Anh Thắng 20/09/2026: nhân viên khai cuối ngày *"còn bao nhiêu bán bao nhiêu"*, hệ
     *"đối chiếu với dữ liệu fabi"*, *"fabi phát sinh món hệ thống tự tách thêm ô nhập"*,
     và *"món nằm trong combo cũng tự hiểu tách ra số lượng tồn kho"*. */
  function taiKho() {
    var o = q('#dtTabKho');
    var ds = (S.cf && S.cf.cua_hang) || [];
    /* Mặc định HÔM NAY, khác tab Nhập báo cáo (hôm qua). Sổ kho là việc CUỐI NGÀY: nhân viên
       đếm kệ lúc đóng cửa rồi khai ngay. Mặc định hôm qua là mỗi tối phải tự đổi ngày, và ai
       quên đổi là số đếm hôm nay đè lên hôm qua — anh Thắng đã vấp: "khi tải lên cứ ghi nhận
       theo ngày trước". */
    if (!S.kho) S.kho = { ngay: homNay(), cs: ds.length ? ds[0] : '' };
    if (!S.kho.cs && ds.length) S.kho.cs = ds[0];
    if (!S.kho.cs) {
      o.innerHTML = '<div class="khung"><div class="trong">Chưa có cơ sở nào để mở sổ kho.</div></div>';
      return;
    }
    /* Giữ số cũ trên màn khi tải lại, y như tab Đối soát — đổi ngày mà màn chớp trắng rồi nhảy
       về đầu trang là đúng chuyện anh Thắng đã kêu một lần. */
    if (!S.khoR) {
      o.innerHTML = '<div class="khung"><div class="trong">Đang tải…</div></div>';
    } else {
      o.setAttribute('aria-busy', 'true');
    }
    var luot = ++khoLuot;
    api('kho?ngay=' + encodeURIComponent(S.kho.ngay) + '&co_so=' + encodeURIComponent(S.kho.cs))
      .then(function (r) {
        if (luot !== khoLuot) return;
        o.removeAttribute('aria-busy');
        S.khoR = r;
        veKho(o, r);
      })
      .catch(function (e) {
        if (luot !== khoLuot) return;
        o.removeAttribute('aria-busy');
        o.innerHTML = '<div class="khung"><div class="trong">' + esc(e.message || e) + '</div></div>';
      });
  }
  var khoLuot = 0;

  function soKho(x) {
    return (x === null || x === undefined || x === '') ? '' : nguyen(x);
  }

  /* Ô lệch: trống khi CHƯA khai, xanh khi khớp, đỏ khi lệch.
     🔴 Trống và 0 phải trông khác nhau. Một ô chưa ai đếm mà hiện "0" xanh lét thì cả sổ trông
        như đã soát xong — đúng điều ngược lại với sự thật. */
  function oLech(v, nhan) {
    var a = ' class="s o-lech" data-nhan="' + esc(nhan || '') + '"';
    if (v === null || v === undefined) return '<td' + a + '><span class="chu-them">—</span></td>';
    var n = Number(v);
    if (!n) return '<td' + a + ' style="color:var(--tot)">0</td>';
    return '<td' + a + ' style="color:var(--xau);font-weight:600">' + (n > 0 ? '+' : '') + nguyen(n) + '</td>';
  }

  function veKho(o, r) {
    var ds = (S.cf && S.cf.cua_hang) || [];
    var ghi = !!r.duoc_ghi;
    var h = '<div class="khung" id="dtKho"><header><h2>Kho hàng hoá</h2>' +
      '<span class="goi">' + esc(r.co_so) + ' · ' + ngayVN(r.ngay) + '</span></header>' +
      '<div class="chu-them">Cuối ngày nhân viên khai <b>bán bao nhiêu</b> và <b>đếm còn bao nhiêu</b>. ' +
      'Hệ đối chiếu với số máy POS ghi (lấy thẳng từ báo cáo FABi đã nạp) và chỉ ra hai chỗ lệch. ' +
      'Danh sách mặt hàng <b>tự sinh từ FABi</b> — món mới xuất hiện là tự có ô nhập.</div>';

    h += '<div class="loc" style="margin:12px 0 4px">' +
      '<label class="o">Ngày<input type="date" id="khoNgay" value="' + esc(r.ngay) + '"></label>' +
      (ds.length > 1
        ? '<label class="o">Cơ sở<select id="khoCS">' + ds.map(function (t) {
            return '<option value="' + esc(t) + '"' + (r.co_so === t ? ' selected' : '') + '>' + esc(t) + '</option>';
          }).join('') + '</select></label>'
        /* 🔴 TÀI KHOẢN MỘT CƠ SỞ VẪN PHẢI THẤY TÊN CƠ SỞ.
           Bản trước để rỗng — trên màn không còn chữ nào nhắc tới cơ sở, mà tên ở tiêu đề
           khung thì cuộn khỏi tầm mắt ngay khi bắt đầu gõ. Anh Thắng nhìn màn điện thoại
           20/09/2026 rồi hỏi thẳng "chưa tách cơ sở à" — số liệu CÓ tách theo cơ sở từ đầu,
           nhưng màn không nói ra thì người dùng không có cách nào biết, và đó cũng là lỗi. */
        : '<span class="o" id="khoCSMot"><label>Cơ sở</label><b>' + esc(r.co_so) + '</b></span>') +
      '</div>';

    /* 🔴 CẢNH BÁO ĐẮT NHẤT CỦA CẢ SỔ: đang trừ kho HAI LẦN.
       FABi đã tách sẵn thành phần combo (dòng có số lượng mà doanh thu 0đ), mà bảng combo lại
       khai thêm — thế là mỗi chai nước bị trừ hai lượt. Ngày nào cũng báo thiếu hàng, người
       trực bị nghi oan, mà không có dòng nào sai để lần ra. */
    /* 🔴 KHÔNG CÒN KẾT LUẬN "FABi ĐÃ TÁCH SẴN COMBO" — bản trước nói thế là nói quá.
       Món máy ghi 0đ có thể là hàng cho / khuyến mãi, cũng có thể là thành phần combo máy đã
       tách. Hệ KHÔNG phân biệt được (xem chú thích dài ở `khh_dt_kho_mon_khong_tien` bên PHP:
       món được cộng gộp theo tên, nên mặt hàng vừa bán lẻ vừa nằm trong combo thì tổng doanh
       thu > 0 và không bao giờ lọt vào danh sách này). Ảnh màn hình anh Thắng gửi chứng minh:
       danh sách toàn "… MIỄN PHÍ" và "VÉ ONLINE", không cái nào là thành phần combo. */
    /* 🔴 CHƯA NẠP BÁO CÁO FABi CHO NGÀY NÀY THÌ NÓI THẲNG, ĐẶT TRÊN CÙNG.
       Không nói thì người trực nhìn cột Máy bán toàn "—" (hoặc toàn 0 như bản trước) rồi tự
       đoán — mà đoán sai theo hướng "hôm nay không bán gì" là đếm xong thấy lệch kho bằng đúng
       số đã bán, rồi tưởng mất hàng. Số đếm vẫn lưu được; nạp báo cáo xong hệ tự tính lại. */
    if (r.co_fabi === false) {
      h += '<div class="canh-ghep" style="margin-top:6px;border-color:var(--xau)">🔴 <b>Chưa nạp ' +
        'báo cáo FABi cho ngày ' + esc(ngayVN(r.ngay)) + '.</b> Cột <b>Máy bán</b> đang trống là vì ' +
        '<b>chưa có số</b>, không phải bán 0. Anh/chị vẫn <b>đếm và Lưu</b> được ngay — số đếm nằm ' +
        'trong sổ; nạp báo cáo FABi xong (thẻ Nạp báo cáo, hoặc hộp thư tự lấy) hệ tự tính tồn ' +
        'và lệch cho ngày này.</div>';
    }

    var kTien = Object.keys(r.mon_khong_tien || {});
    if (kTien.length) {
      h += '<div class="canh-ghep" style="margin-top:6px">ℹ️ <b>' + kTien.length +
        ' món máy ghi số lượng mà doanh thu 0đ:</b> ' +
        esc(kTien.slice(0, 8).join(' · ')) + (kTien.length > 8 ? ' …' : '') +
        '.<br>Có thể là <b>hàng cho / khuyến mãi</b>, cũng có thể là <b>thành phần combo máy đã ' +
        'tách sẵn</b> — hệ không phân biệt được hai thứ ấy. Dù là gì thì chúng <b>vẫn rời kho</b> ' +
        'và đã được tính vào cột "Máy bán tổng".' +
        ((r.tru_hai_lan || []).length
          ? '<br>🔴 <b>Xem lại kẻo trừ hai lần:</b> ' + r.tru_hai_lan.map(esc).join(' · ') +
            ' vừa có dòng 0đ của máy, vừa đang được khai trong <b>Thành phần combo</b>. Nếu dòng ' +
            '0đ ấy là thành phần combo máy đã tách thì khai thêm là trừ hai lượt — xoá khai combo đi.'
          : '') +
        '</div>';
    }

    if ((r.combo_nghi || []).length && ghi) {
      h += '<div class="canh-ghep" style="margin-top:6px">⚠️ <b>Chưa khai thành phần combo:</b> ' +
        r.combo_nghi.map(esc).join(' · ') +
        '.<br>Combo bán ra là hàng rời kho, nhưng FABi ghi doanh thu vào tên combo chứ không vào ' +
        'tên chai nước. Chưa khai thành phần thì mấy món trong đó <b>mãi mãi "chưa bán"</b> và tồn ' +
        'tính cứ thừa dần. Khai ở khối <b>Thành phần combo</b> dưới cùng.</div>';
    }

    var dong = r.dong || [];
    var so_lan = r.so_lan || {};
    if (!dong.length) {
      h += '<div class="trong" style="margin-top:12px">Ngày này chưa có món nào của FABi, và kho cũng chưa có tồn.</div>';
    } else {
      /* 🔴 MỘT DOM, HAI CÁCH BÀY — bảng trên máy tính, thẻ dọc trên điện thoại, và cả hai
         dùng CHUNG một đoạn markup. Dựng hai bản markup riêng rồi chọn theo bề ngang màn là
         sớm muộn sửa một bên quên bên kia, mà bên quên lại đúng là bên nhân viên dùng hằng
         ngày ngoài cửa hàng — không ai ngồi máy tính mở màn này.
         Mỗi ô mang `data-nhan`: trên điện thoại cái nhãn ấy chính là đầu cột, vì hàng `thead`
         bị ẩn đi. Thiếu `data-nhan` là thẻ hiện ra một cột số trần không ai đọc nổi. */
      h += '<div class="bang-cuon bang-the" style="margin-top:8px"><table><thead><tr>' +
        '<th style="text-align:left">Mặt hàng</th>' +
        '<th>Tồn đầu</th><th>Nhập</th>' +
        '<th>Máy bán lẻ</th><th>Theo combo</th><th>Máy bán tổng</th>' +
        '<th>Hàng huỷ</th>' +
        '<th>Tồn tính</th><th>Hàng tồn còn</th><th>Lệch kho</th>' +
        '<th style="text-align:left">Ghi chú</th>' +
        '</tr></thead><tbody>' +
        dong.map(function (d, i) {
          /* Ba ô PHẢI gõ — trên điện thoại chúng nổi lên thành hàng ô to, chiếm hết bề ngang. */
          var oNhap = function (ten, nhan, gt) {
            return '<td class="o-go" data-nhan="' + esc(nhan) + '">' + (ghi
              ? '<input type="text" inputmode="numeric" data-kho="' + ten + '" data-i="' + i +
                '" value="' + esc(gt === null || gt === undefined ? '' : gt) + '">'
              : '<span class="s">' + soKho(gt) + '</span>') + '</td>';
          };
          /* Số của máy — chỉ để đọc, trên điện thoại thu lại thành mấy con chữ nhỏ nằm một hàng. */
          /* 🔴 `null` = CHƯA BIẾT, phải hiện "—". In 0 hay in số âm ở đây là bịa ra một con
             số hệ không hề biết — và anh Thắng đã thấy đúng cảnh ấy: cả màn "−61", "−139".
             Chưa ai đặt mốc thì không có gì để tính, nói thẳng thế. */
          var oMay = function (nhan, gt, dam) {
            var t = (gt === null || gt === undefined)
              ? '<span class="chu-them">—</span>'
              : (dam ? '<b>' + nguyen(gt) + '</b>' : nguyen(gt));
            return '<td class="s o-may" data-nhan="' + esc(nhan) + '">' + t + '</td>';
          };
          /* Tên mặt hàng bấm được -> mở THẺ KHO: từng ngày tồn đầu / nhập / bán / đếm / tồn cuối.
             Anh Thắng: "tồn kho ngày đó bao nhiêu, bán bao nhiêu, tồn bao nhiêu" — màn này chỉ
             cho một ngày, muốn thấy hàng chạy thì phải có thẻ kho. */
          return '<tr data-dong="' + i + '"><td class="o-ten" data-nhan="Mặt hàng">' +
            '<a href="#" data-kho-the="' + esc(d.mat_hang) + '" title="Mở thẻ kho: xem mặt hàng này chạy từng ngày" ' +
            'style="color:inherit;text-decoration:underline dotted">' + esc(d.mat_hang) + '</a>' +
            (d.co_moc ? '' : ' <span class="chip" title="Chưa ai đếm mặt hàng này bao giờ, nên hệ chưa biết trên kệ có bao nhiêu. Gõ số đếm được vào ô &quot;Hàng tồn còn&quot; một lần là xong — từ hôm sau hệ tự tính.">đếm 1 lần để đặt mốc</span>') +
            /* 🔴 GIỮ VẾT MÀ KHÔNG BÀY RA THÌ CHẲNG AI BIẾT LÀ CÓ VẾT.
               Sổ ghi động giữ đủ mọi lượt khai, nhưng nếu màn không nói thì người trực vẫn
               tưởng sửa là xoá dấu — và người soát cũng không nghĩ tới chuyện đi xem lịch sử.
               Nhãn này chính là phần răn: nó hiện ngay cạnh tên mặt hàng. */
            ((so_lan[d.mat_hang] || 0) > 1
              ? ' <button class="chip" type="button" data-kho-su="' + esc(d.mat_hang) +
                '" style="cursor:pointer;border-color:var(--xau);color:var(--xau)" ' +
                'title="Dòng này đã được khai lại nhiều lượt. Bấm để xem đủ các lượt, kèm người và giờ.">' +
                'đã sửa ' + (so_lan[d.mat_hang] - 1) + ' lần</button>'
              : '') +
            '</td>' +
            /* Tồn đầu GÕ ĐƯỢC (anh Thắng 24/09/2026: "cho set lại tồn đầu"): trống = theo tồn cuối
               hôm trước (số hiện mờ trong ô); gõ số = đặt mốc mới cho ngày này, kể cả 0. */
            (ghi
              ? '<td class="o-go o-dau" data-nhan="Tồn đầu"><input type="text" inputmode="numeric" data-kho="dat_dau" data-i="' + i +
                '" value="' + esc(d.dat_dau === null || d.dat_dau === undefined ? '' : d.dat_dau) + '" placeholder="' + esc(soKho(d.ton_dau)) +
                '" title="Để trống = theo tồn cuối hôm trước. Gõ số để đặt lại tồn đầu ngày này."></td>'
              : oMay('Tồn đầu', d.ton_dau)) +
            oNhap('nhap', 'Nhập', d.nhap) +
            oMay('Máy bán lẻ', d.ban_le) +
            oMay('Theo combo', d.ban_combo) +
            (d.ban_chot
              ? '<td class="s o-may" data-nhan="Máy bán tổng" title="Lấy SL thực cơ sở đã chốt ở tab Nhập báo cáo (khác máy)"><b>' + soKho(d.ban_may) + '*</b></td>'
              : oMay('Máy bán tổng', d.ban_may, true)) +
            /* Hàng huỷ (anh Thắng 24/09/2026): hỏng/đổ/vỡ bỏ đi, trừ khỏi tồn. Cột "SL hàng bán / Lệch khai"
               cũ bỏ: soát bán so máy đã làm ở tab Nhập báo cáo, sổ kho lấy đúng SL thực đã chốt bên ấy. */
            oNhap('huy', 'Hàng huỷ', d.huy ? d.huy : null) +
            oMay('Tồn tính', d.ton_tinh) +
            oNhap('dem', 'Hàng tồn còn', d.dem) +
            oLech(d.lech_kho, 'Lệch kho') +
            '<td class="o-ghi" data-nhan="Ghi chú">' + (ghi
              ? '<input type="text" data-kho="ghi_chu" data-i="' + i + '" value="' + esc(d.ghi_chu || '') + '">'
              : esc(d.ghi_chu || '')) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
      if (ghi) {
        h += '<div style="margin-top:10px"><button class="nut chinh" type="button" id="khoLuu">Lưu sổ kho</button></div>';
      }
      h += '<div class="chu-them" style="margin-top:8px">' +
        '<b>Tồn đầu</b>: số mờ là tồn cuối hôm trước kéo sang; thấy sai (âm, lệch) thì <b>gõ số thật vào ô</b> để đặt lại mốc cho ngày này — từ đó hệ tính tiếp. Để trống là giữ số kéo.<br>' +
        '<b>Hàng huỷ</b> = hàng hỏng, đổ, vỡ bỏ đi — rời kho không qua máy, trừ thẳng khỏi tồn.<br>' +
        '<b>Máy bán</b>: nếu cơ sở đã chốt <b>SL thực</b> khác máy ở tab Nhập báo cáo thì sổ kho lấy số chốt ấy (ô đánh dấu *).<br>' +
        '<b>Tồn tính</b> = tồn đầu + nhập − máy POS ghi bán − combo nhập tay − hàng huỷ. ' +
        '<b>Lệch kho</b> = hàng tồn còn (đếm được) − tồn tính. Âm là thiếu hàng.<br>' +
        '🔴 Tồn tính lấy <b>số máy</b>, không lấy số nhân viên khai — lấy số khai thì người khai ' +
        'thiếu bao nhiêu tồn tính cũng thừa bấy nhiêu, hai vế triệt tiêu và cột lệch luôn bằng 0.<br>' +
        'Ngày mai <b>tồn đầu lấy số đã đếm</b> chứ không lấy số tính, nên một ngày lệch không kéo ' +
        'theo mọi ngày sau.</div>';
    }

    h += '<div id="khoThe"></div>';
    if (ghi) { h += veKhoMatHang(r); h += veKhoCombo(r.combo || {}, r); }
    h += '</div>';
    o.innerHTML = h;
    noiKho(o);
  }

  /* ---- CHỌN MẶT HÀNG CÓ KHO CỦA CƠ SỞ ----
     Anh Thắng 20/09/2026: *"Phân loại theo cơ sở đang có hàng của mình nhé"*. FABi bán cả
     BẠC XỈU, CACAO LATTE, COMBO TRÀ CHANH GIÃ TAY — đồ pha tại chỗ, không có kho để đếm. Đổ
     hết vào sổ thì nhân viên cuộn qua vài chục dòng vô nghĩa mới tới chai nước, và mấy dòng
     ấy mãi mãi đỏ vì chẳng ai đếm chúng bao giờ. Sổ đỏ vì lý do vớ vẩn là sổ bị bỏ. */
  /* ---- THẺ KHO: một mặt hàng chạy từng ngày ---- */
  function veKhoThe(o, r) {
    var noi = o.querySelector('#khoThe');
    if (!noi) return;
    var ds = r.dong || [];
    var h = '<div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line)">' +
      '<h3 class="tieu-nho">Thẻ kho: ' + esc(r.mat_hang) + '</h3>' +
      '<div class="loc" style="gap:6px;margin:6px 0">' +
        '<label class="o">Từ<input type="date" id="theTu" value="' + esc(r.tu) + '"></label>' +
        '<label class="o">Đến<input type="date" id="theDen" value="' + esc(r.den) + '"></label>' +
        '<button class="vien" type="button" id="theDong">Đóng</button>' +
      '</div>';
    if (!ds.length) {
      h += '<div class="trong">Khoảng này không có ngày nào có biến động (không bán, không nhập, không đếm).</div>';
    } else {
      /* Cột "Tồn cuối" = số hệ CHỐT cho ngày ấy: có đếm thì là số đếm (mốc mới), không thì là
         số tính. Bày cả "Tồn tính" bên cạnh để thấy ngay ngày nào đếm khác tính. */
      h += '<div class="bang-cuon bang-the"><table><thead><tr>' +
        '<th style="text-align:left">Ngày</th><th>Tồn đầu</th><th>Nhập</th><th>Máy bán</th>' +
        '<th>Combo tay</th><th>Tồn tính</th><th>Đếm</th><th>Lệch</th><th>Tồn cuối</th>' +
        '</tr></thead><tbody>' +
        ds.map(function (x) {
          var c = function (nhan, v, dam) {
            var t = (v === null || v === undefined) ? '<span class="chu-them">—</span>'
              : (dam ? '<b>' + nguyen(v) + '</b>' : nguyen(v));
            return '<td class="s o-may" data-nhan="' + nhan + '">' + t + '</td>';
          };
          return '<tr' + (x.co_fabi ? '' : ' title="Ngày này chưa nạp báo cáo FABi — máy bán coi là 0 để còn kéo tồn sang ngày sau"') + '>' +
            '<td class="o-ten" data-nhan="Ngày">' + esc(ngayVN(x.ngay)) +
              (x.co_fabi ? '' : ' <span class="chip" style="border-color:var(--xau);color:var(--xau)">chưa nạp FABi</span>') +
            '</td>' +
            c('Tồn đầu', x.ton_dau) + c('Nhập', x.nhap) + c('Máy bán', x.ban_may) +
            c('Combo tay', x.combo_tay) + c('Tồn tính', x.ton_tinh) + c('Đếm', x.dem) +
            oLech(x.lech_kho, 'Lệch') + c('Tồn cuối', x.ton_cuoi, true) +
            '</tr>';
        }).join('') + '</tbody></table></div>';
    }
    h += '</div>';
    noi.innerHTML = h;
    var tai = function () {
      taiKhoThe(o, r.mat_hang, (noi.querySelector('#theTu') || {}).value, (noi.querySelector('#theDen') || {}).value);
    };
    noiONgay(noi.querySelector('#theTu'), tai);
    noiONgay(noi.querySelector('#theDen'), tai);
    var d = noi.querySelector('#theDong');
    if (d) d.addEventListener('click', function () { noi.innerHTML = ''; S.khoTheMH = ''; });
    if (noi.scrollIntoView) noi.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  function taiKhoThe(o, mh, tu, den) {
    S.khoTheMH = mh;
    var den2 = den || S.kho.ngay;
    var tu2 = tu || doi(den2, -30);
    api('kho-the?co_so=' + encodeURIComponent(S.kho.cs) + '&mat_hang=' + encodeURIComponent(mh) +
        '&tu=' + encodeURIComponent(tu2) + '&den=' + encodeURIComponent(den2))
      .then(function (r) { veKhoThe(o, r); })
      .catch(function (e) { window.alert(e.message || e); });
  }

  function veKhoMatHang(r) {
    var daThay = r.mon_da_thay || {};
    var chon = r.mat_hang || [];
    var maHang = r.ma_hang || {};
    /* Danh sách bày = món FABi từng bán ∪ món đã có trong danh mục (kể cả món MỚI thêm tay, FABi chưa
       bán) — không thì món mới thêm không có ô để bỏ tích. */
    var ten = Object.keys(daThay);
    chon.forEach(function (t) { if (ten.indexOf(t) < 0) ten.push(t); });
    ten.sort(function (a, b) { return a.localeCompare(b, 'vi'); });
    return '<details style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line)"' +
      (chon.length ? '' : ' open') + '>' +
      '<summary style="cursor:pointer"><b>Mặt hàng có kho của cơ sở này</b> — ' +
      (chon.length
        ? 'đang theo dõi ' + chon.length + '/' + ten.length + ' món'
        : '<b>chưa chọn — đang bày hết ' + ten.length + ' món</b>') + '</summary>' +
      '<div class="chu-them" style="margin-top:6px">Tích những món <b>có hàng để đếm trên kệ</b> ' +
      '(nước, kẹo, bimbim, đồ chơi…). Bỏ qua đồ pha tại chỗ và combo — không có kho thì không ' +
      'đếm được, mà để trong sổ thì ngày nào cũng đỏ vì chẳng ai đếm chúng.' +
      '<br>Số trong ngoặc là <b>số lượng bán 90 ngày qua</b>, để biết món nào đáng theo dõi. ' +
      'Bỏ tích hết rồi Lưu là thôi lọc, bày lại tất cả.</div>' +
      (ten.length
        ? '<div style="margin-top:8px;max-height:320px;overflow-y:auto;display:grid;' +
          'grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px 12px">' +
          ten.map(function (t) {
            var moi = !(t in daThay);
            return '<label class="o" style="justify-content:flex-start;gap:7px;padding:6px 8px">' +
              '<input type="checkbox" data-mh="' + esc(t) + '"' +
              (chon.indexOf(t) >= 0 ? ' checked' : '') + '>' +
              '<span>' + esc(t) +
                (maHang[t] ? ' <code style="font-size:11px">' + esc(maHang[t]) + '</code>' : '') +
                (moi ? ' <span class="chip" title="Thêm tay, FABi chưa có dòng bán nào. Khi FABi bán món mang đúng mã này, số bán tự rơi vào dòng kho này.">mới · FABi chưa bán</span>' +
                       /* Anh Thắng 24/09/2026: "cho admin xoá món nếu sai" — chỉ văn phòng (quyền nạp). */
                       (S.cf && S.cf.duoc_nap ? ' <button class="chip" type="button" data-mh-xoa="' + esc(t) + '" title="Xoá món thêm tay này khỏi danh mục (bỏ cả mã đã gán)">✕ xoá</button>' : '')
                     : ' <span class="chu-them">(' + nguyen(daThay[t]) + ')</span>') +
              '</span></label>';
          }).join('') +
          '</div>'
        : '<div class="trong">Chưa có món nào — thêm mặt hàng mới ở dưới.</div>') +
      /* THÊM SẢN PHẨM MỚI (anh Thắng 24/09/2026: "lấy tên sản phẩm mà mã theo FABi, để sau đồng bộ nó
         chạy cùng"): hàng mới về chưa bán -> FABi chưa có dòng -> không tích được từ danh sách trên. */
      '<div class="loc" style="margin-top:10px;gap:6px">' +
        '<label class="o">Thêm mặt hàng mới<input type="text" id="mhThemTen" placeholder="Tên đúng như FABi sẽ ghi" style="width:230px"></label>' +
        '<label class="o">Mã hàng FABi<input type="text" id="mhThemMa" placeholder="MNKVCDS017" style="width:130px"></label>' +
        '<button class="nut" type="button" id="mhThem">Thêm vào danh mục</button>' +
        '<span class="chu-them" style="margin:0">Khai đúng <b>mã hàng</b> là về sau FABi bán món này (dù tên gõ khác chút) số vẫn rơi vào đúng dòng.</span>' +
      '</div>' +
      '<div style="margin-top:8px">' +
        '<button class="nut" type="button" id="mhLuu">Lưu danh mục</button> ' +
        '<button class="vien" type="button" id="mhHet">Bỏ tích hết</button>' +
      '</div></details>';
  }

  function veKhoCombo(cb, r) {
    r = r || {};
    var ten = Object.keys(cb).sort();
    /* COMBO ĐANG BÁN: chọn từ danh sách, không gõ. Anh Thắng 24/09/2026: *"hiện combo đang chạy và
       thành phần đang bán, mới hiểu được combo đó có hàng bán gì để trừ, chứ nhập hay ghi sai tên sản
       phẩm"*. Ứng viên = combo hệ nghi (đang bán mà chưa khai) ∪ món FABi có chữ "combo" ∪ combo đã khai. */
    var daThay = r.mon_da_thay || {};
    var ungVien = [];
    (r.combo_nghi || []).forEach(function (t) { if (ungVien.indexOf(t) < 0) ungVien.push(t); });
    Object.keys(daThay).forEach(function (t) { if (/combo/i.test(t) && ungVien.indexOf(t) < 0) ungVien.push(t); });
    ten.forEach(function (t) { if (ungVien.indexOf(t) < 0) ungVien.push(t); });
    ungVien.sort(function (a, b) { return a.localeCompare(b, 'vi'); });
    /* THÀNH PHẦN ĐANG BÁN: tích từ danh mục kho (không có danh mục thì mọi món FABi từng bán), mỗi món
       một ô số lượng. */
    var mon = (r.mat_hang && r.mat_hang.length) ? r.mat_hang.slice() : Object.keys(daThay);
    mon = mon.filter(function (t) { return !/combo/i.test(t); });
    mon.sort(function (a, b) { return a.localeCompare(b, 'vi'); });
    return '<details style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line)"' +
      (ten.length ? '' : ' open') + '>' +
      '<summary style="cursor:pointer"><b>Thành phần combo</b> — ' +
      (ten.length ? ten.length + ' combo đã khai' : '<b>chưa khai combo nào</b>') + '</summary>' +
      '<div class="chu-them" style="margin-top:6px">Một combo bán ra là mấy món rời kho. <b>Chọn combo</b> đang bán ' +
      'trong danh sách (hệ lấy từ FABi, không phải gõ tên), rồi <b>gõ số lượng</b> vào ô của từng món thành phần ' +
      'đang có trong danh mục kho. Bỏ trống hết rồi Lưu là xoá công thức combo ấy.' +
      '<br>🔴 Hệ <b>không tự đoán</b> công thức: đoán sai là trừ nhầm kho hàng loạt mà không dòng nào sai.</div>' +
      (ten.length
        ? '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          '<th style="text-align:left">Món combo</th><th style="text-align:left">Thành phần</th>' +
          '</tr></thead><tbody>' + ten.map(function (t) {
            /* Thành phần không trùng tên món nào trong kho (gõ tay sai tên: "bimbim", "nước suối") thì
               combo bán ra KHÔNG trừ vào dòng nào — phải đỏ lên để khai lại bằng chọn. */
            var coKho = mon.concat(Object.keys(daThay));
            return '<tr><td style="text-align:left">' + esc(t) + '</td>' +
              '<td style="text-align:left" class="s">' + Object.keys(cb[t]).map(function (m) {
                var khop = coKho.indexOf(m) >= 0;
                return (khop ? esc(m) : '<b style="color:var(--xau)" title="Không trùng tên món nào trong kho hay FABi — combo này bán ra không trừ được dòng nào. Chọn lại combo ở dưới, tích đúng món rồi Lưu.">' + esc(m) + ' ⚠ không khớp món nào</b>') + ' x' + cb[t][m];
              }).join(', ') + '</td></tr>';
          }).join('') + '</tbody></table></div>'
        : '') +
      '<div class="loc" style="margin-top:10px;gap:6px">' +
        '<label class="o">Món combo<select id="cbChon" style="max-width:320px">' +
          '<option value="">— chọn combo đang bán —</option>' +
          ungVien.map(function (t) { return '<option value="' + esc(t) + '">' + esc(t) + (cb[t] ? ' ✓' : '') + (daThay[t] ? ' (' + nguyen(daThay[t]) + ')' : '') + '</option>'; }).join('') +
          '<option value="__khac__">Khác — gõ tên…</option></select></label>' +
        '<label class="o" id="cbTenO" hidden>Tên combo<input type="text" id="cbTen" placeholder="đúng như FABi ghi" style="width:190px"></label>' +
      '</div>' +
      '<div id="cbTP_bang" style="margin-top:6px;max-height:260px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:4px 12px">' +
        mon.map(function (t) {
          return '<label class="o" style="justify-content:space-between;gap:7px;padding:4px 8px"><span>' + esc(t) + '</span>' +
            '<input type="number" min="0" step="1" inputmode="numeric" data-cb-mon="' + esc(t) + '" placeholder="0" style="width:64px"></label>';
        }).join('') +
      '</div>' +
      '<div class="loc" style="margin-top:6px;gap:6px">' +
        '<label class="o">Thêm nhanh<input type="text" id="cbTP" placeholder="tuỳ chọn: Món lẻ x2, Món khác x1" style="width:260px" title="Món chưa có trong danh mục — gõ đúng tên FABi"></label>' +
        /* 🔴 NGÀY HIỆU LỰC, MẶC ĐỊNH HÔM NAY. Công thức đóng băng theo ngày nên sửa hôm nay
           KHÔNG viết lại số tồn của những ngày trước — muốn áp lùi thì phải tự gõ ngày, vì
           áp lùi là cố ý sửa lại quá khứ. */
        '<label class="o">Áp từ ngày<input type="date" id="cbTu" value="' + esc(homNay()) + '"></label>' +
        '<button class="nut" type="button" id="cbLuu">Lưu combo</button>' +
      '</div></details>';
  }

  /* "Nước suối x2, Kẹo cầu vồng x1" -> { "Nước suối": 2, "Kẹo cầu vồng": 1 }.
     Không có "xN" thì coi là 1 — người ta hay gõ mỗi tên món. */
  function docThanhPhan(s) {
    var ra = {};
    String(s || '').split(',').forEach(function (x) {
      var t = x.trim();
      if (!t) return;
      var m = t.match(/^(.*?)\s*[x\*]\s*(\d+(?:[.,]\d+)?)$/i);
      if (m) { ra[m[1].trim()] = parseFloat(m[2].replace(',', '.')); }
      else { ra[t] = 1; }
    });
    return ra;
  }

  /* TÍNH LẠI MỘT DÒNG KHO NGAY KHI GÕ — anh Thắng 24/09/2026: *"nhập tồn mà sao nó không tính realtime
     trước và sau của ngày đó"*. Cùng công thức với máy chủ (`khh_dt_kho_bang_ngay`): tồn đầu (ô đặt lại
     nếu có, không thì số kéo) + nhập − máy bán − combo nhập tay − huỷ = tồn tính; hàng tồn còn − tồn
     tính = lệch kho, chỉ khi có gõ hàng tồn còn. `d` là dòng máy chủ trả, `o` là các ô đang gõ. Máy
     chủ vẫn là nơi quyết định khi Lưu — chỗ này chỉ để mắt thấy ngay. */
  function tinhKhoDong(d, o) {
    var so = function (v) {
      if (v === null || v === undefined) return null;
      var t = String(v).replace(/[^\d\-.,]/g, '');
      // "1.000" / "1,000" là ngăn nghìn (đúng 3 chữ số sau dấu) -> bỏ dấu; còn lại dấu phẩy là thập phân.
      t = t.replace(/[.,](?=\d{3}(?:[.,]|$))/g, '').replace(',', '.');
      if (t === '' || t === '-') return null;
      var n = parseFloat(t); return isNaN(n) ? null : n;
    };
    var datDau = so(o.dat_dau);
    var tonDau = datDau !== null ? datDau : (d.ton_dau === null || d.ton_dau === undefined ? null : Number(d.ton_dau));
    var nhap = so(o.nhap) || 0, huy = so(o.huy) || 0;
    var goc = tonDau === null && nhap > 0 ? 0 : tonDau;
    var may = d.ban_may === null || d.ban_may === undefined ? null : Number(d.ban_may);
    var tonTinh = (goc === null || may === null) ? null : goc + nhap - may - Number(d.combo_tay || 0) - huy;
    var dem = so(o.dem);
    var lech = (dem === null || tonTinh === null) ? null : dem - tonTinh;
    return { ton_tinh: tonTinh, lech_kho: lech };
  }

  function veLaiDongKho(tr) {
    if (!tr || !S.khoR || !S.khoR.dong) return;
    var d = S.khoR.dong[parseInt(tr.getAttribute('data-dong'), 10)];
    if (!d) return;
    var o = {};
    Array.prototype.forEach.call(tr.querySelectorAll('[data-kho]'), function (x) { o[x.getAttribute('data-kho')] = x.value; });
    var kq = tinhKhoDong(d, o);
    var oTinh = tr.querySelector('.o-may[data-nhan="Tồn tính"]');
    if (oTinh) oTinh.innerHTML = kq.ton_tinh === null ? '<span class="chu-them">—</span>' : nguyen(kq.ton_tinh);
    var oLechKho = tr.querySelector('.o-lech[data-nhan="Lệch kho"]');
    if (oLechKho) {
      var tmp = document.createElement('tbody');
      tmp.innerHTML = '<tr>' + oLech(kq.lech_kho, 'Lệch kho') + '</tr>';
      oLechKho.replaceWith(tmp.querySelector('td'));
    }
  }

  function noiKho(o) {
    var k = o.querySelector('#dtKho');
    if (!k) return;
    /* Gõ ô nào trong dòng là tồn tính / lệch kho của dòng ấy đổi ngay. */
    k.addEventListener('input', function (e) {
      var x = e.target;
      if (!x || !x.getAttribute || !x.getAttribute('data-kho')) return;
      var kho = x.getAttribute('data-kho');
      if (kho !== 'dat_dau' && kho !== 'nhap' && kho !== 'huy' && kho !== 'dem') return;
      veLaiDongKho(x.closest('tr'));
    });
    noiONgay(k.querySelector('#khoNgay'), function (v) { S.kho.ngay = v; taiKho(); });
    var cs = k.querySelector('#khoCS');
    if (cs) cs.addEventListener('change', function () { S.kho.cs = cs.value; taiKho(); });

    var luu = k.querySelector('#khoLuu');
    if (luu) {
      luu.addEventListener('click', function () {
        var dong = (S.khoR.dong || []).map(function (d) { return { mat_hang: d.mat_hang }; });
        Array.prototype.forEach.call(k.querySelectorAll('[data-kho]'), function (x) {
          var i = parseInt(x.getAttribute('data-i'), 10);
          if (!dong[i]) return;
          dong[i][x.getAttribute('data-kho')] = x.value.trim();
        });
        var fd = new FormData();
        fd.append('ngay', S.kho.ngay);
        fd.append('co_so', S.kho.cs);
        fd.append('dong', JSON.stringify(dong));
        luu.disabled = true; luu.textContent = 'Đang lưu…';
        api('kho', { method: 'POST', body: fd }).then(function (r) {
          S.khoR = r; veKho(o, r);
        }).catch(function (e) {
          luu.disabled = false; luu.textContent = 'Lưu sổ kho';
          window.alert(e.message || e);
        });
      });
    }

    /* Bấm tên mặt hàng -> thẻ kho. */
    k.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-kho-the]') : null;
      if (!a) return;
      e.preventDefault();
      taiKhoThe(o, a.getAttribute('data-kho-the'));
    });

    /* Xem đủ các lượt khai của một dòng — kèm người và giờ, để còn đối chất được. */
    k.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-kho-su]') : null;
      if (!b) return;
      e.preventDefault();
      var mh = b.getAttribute('data-kho-su');
      api('kho-su?ngay=' + encodeURIComponent(S.kho.ngay) +
          '&co_so=' + encodeURIComponent(S.kho.cs) +
          '&mat_hang=' + encodeURIComponent(mh))
        .then(function (rr) {
          var ds = (rr && rr.su) || [];
          if (!ds.length) { window.alert('Không có lượt khai nào.'); return; }
          window.alert(mh + ' — ' + ds.length + ' lượt khai, mới nhất trước:\n\n' +
            ds.map(function (x, i) {
              return (i + 1) + '. ' + (x.luc || '') + (x.nguoi ? ' · ' + x.nguoi : '') +
                '\n   nhập ' + soKho(x.nhap) +
                ' · huỷ ' + (soKho(x.huy) || '—') +
                ' · đếm còn ' + (soKho(x.dem) || '—') +
                (x.ghi_chu ? '\n   ghi chú: ' + x.ghi_chu : '');
            }).join('\n'));
        })
        .catch(function (err) { window.alert(err.message || err); });
    });

    var mhH = k.querySelector('#mhHet');
    if (mhH) {
      mhH.addEventListener('click', function () {
        Array.prototype.forEach.call(k.querySelectorAll('[data-mh]'), function (x) { x.checked = false; });
      });
    }
    var mhL = k.querySelector('#mhLuu');
    if (mhL) {
      mhL.addEventListener('click', function () {
        var ds = [];
        Array.prototype.forEach.call(k.querySelectorAll('[data-mh]'), function (x) {
          if (x.checked) ds.push(x.getAttribute('data-mh'));
        });
        var fd = new FormData();
        fd.append('co_so', S.kho.cs);
        fd.append('ngay', S.kho.ngay);
        fd.append('ds', JSON.stringify(ds));
        mhL.disabled = true; mhL.textContent = 'Đang lưu…';
        /* Đổi danh mục là đổi hẳn danh sách dòng của sổ, nên nạp lại cả màn. */
        api('kho-mat-hang', { method: 'POST', body: fd }).then(function (rr) {
          S.khoR = rr; veKho(o, rr);
        }).catch(function (e) {
          mhL.disabled = false; mhL.textContent = 'Lưu danh mục';
          window.alert(e.message || e);
        });
      });
    }

    Array.prototype.forEach.call(k.querySelectorAll('[data-mh-xoa]'), function (bx) {
      bx.addEventListener('click', function (ev) {
        ev.preventDefault();
        var tenXoa = bx.getAttribute('data-mh-xoa');
        if (!window.confirm('Xoá "' + tenXoa + '" khỏi danh mục kho của cơ sở này? Số đã khai (nếu có) vẫn nằm trong sổ ghi động.')) return;
        var ds = [];
        Array.prototype.forEach.call(k.querySelectorAll('[data-mh]'), function (x) { if (x.checked && x.getAttribute('data-mh') !== tenXoa) ds.push(x.getAttribute('data-mh')); });
        var fd = new FormData();
        fd.append('co_so', S.kho.cs); fd.append('ngay', S.kho.ngay);
        fd.append('ds', JSON.stringify(ds)); fd.append('xoa_ten', tenXoa);
        api('kho-mat-hang', { method: 'POST', body: fd }).then(function (rr) {
          S.khoR = rr; veKho(o, rr);
        }).catch(function (e) { window.alert(e.message || e); });
      });
    });

    var mhT = k.querySelector('#mhThem');
    if (mhT) {
      mhT.addEventListener('click', function () {
        var tenMoi = ((k.querySelector('#mhThemTen') || {}).value || '').trim();
        var maMoi = ((k.querySelector('#mhThemMa') || {}).value || '').trim();
        if (!tenMoi) { window.alert('Gõ tên mặt hàng đúng như FABi sẽ ghi.'); return; }
        /* Giữ những món đang tích, cộng thêm món mới (tự tích). */
        var ds = [];
        Array.prototype.forEach.call(k.querySelectorAll('[data-mh]'), function (x) { if (x.checked) ds.push(x.getAttribute('data-mh')); });
        if (ds.indexOf(tenMoi) < 0) ds.push(tenMoi);
        var fd = new FormData();
        fd.append('co_so', S.kho.cs); fd.append('ngay', S.kho.ngay);
        fd.append('ds', JSON.stringify(ds)); fd.append('them_ten', tenMoi); fd.append('them_ma', maMoi);
        mhT.disabled = true; mhT.textContent = 'Đang thêm…';
        api('kho-mat-hang', { method: 'POST', body: fd }).then(function (rr) {
          S.khoR = rr; veKho(o, rr);
        }).catch(function (e) {
          mhT.disabled = false; mhT.textContent = 'Thêm vào danh mục'; window.alert(e.message || e);
        });
      });
    }

    var cbL = k.querySelector('#cbLuu');
    if (cbL) {
      /* Chọn combo -> điền sẵn công thức đã khai (nếu có) vào các ô số lượng; "Khác" -> mở ô gõ tên. */
      var cbSel = k.querySelector('#cbChon');
      if (cbSel) cbSel.addEventListener('change', function () {
        var v = cbSel.value, oTen = k.querySelector('#cbTenO');
        if (oTen) oTen.hidden = v !== '__khac__';
        var ct = (S.khoR && S.khoR.combo && S.khoR.combo[v]) || {};
        Array.prototype.forEach.call(k.querySelectorAll('[data-cb-mon]'), function (i) {
          var m = i.getAttribute('data-cb-mon');
          i.value = ct[m] != null ? ct[m] : '';
        });
      });
      cbL.addEventListener('click', function () {
        var chon = (k.querySelector('#cbChon') || {}).value || '';
        var ten = chon === '__khac__' || !chon ? ((k.querySelector('#cbTen') || {}).value || '') : chon;
        if (!ten.trim()) { window.alert('Chọn combo đang bán trong danh sách (hoặc chọn "Khác" rồi gõ đúng tên FABi).'); return; }
        var tp = docThanhPhan((k.querySelector('#cbTP') || {}).value);
        Array.prototype.forEach.call(k.querySelectorAll('[data-cb-mon]'), function (i) {
          var v = parseFloat(String(i.value).replace(',', '.'));
          if (v > 0) tp[i.getAttribute('data-cb-mon')] = v;
        });
        var fd = new FormData();
        fd.append('ten', ten.trim());
        fd.append('thanh_phan', JSON.stringify(tp));
        fd.append('tu_ngay', (k.querySelector('#cbTu') || {}).value || '');
        cbL.disabled = true; cbL.textContent = 'Đang lưu…';
        /* 🔴 Đổi thành phần combo là đổi cách trừ kho của MỌI ngày, nên phải nạp LẠI cả sổ —
           không thì màn vẫn bày số tính theo công thức cũ. */
        api('kho-combo', { method: 'POST', body: fd }).then(taiKho).catch(function (e) {
          cbL.disabled = false; cbL.textContent = 'Lưu combo';
          window.alert(e.message || e);
        });
      });
    }
  }

  /* ================= tab ĐỐI SOÁT ================= */
  var dsLuot = 0;   // đếm lượt gọi, xem chú thích trong `taiDoiSoat`
  function taiDoiSoat() {
    var o = q('#dtTabDoiSoat');
    var k = tinhKyCua(S.ds);
    /* 🔴 LƯỢT TRẢ VỀ CŨ KHÔNG ĐƯỢC ĐÈ LƯỢT MỚI. Đổi ngày rồi đổi tiếp là hai lượt hỏi chạy
       song song; lượt đầu về sau thì màn hiện số của khoảng CŨ, mà thanh ngày lại ghi khoảng
       MỚI — sai mà trông như thật. Đánh số lượt, về trễ thì bỏ. */
    var luot = ++dsLuot;
    /* 🔴 CHỈ XOÁ TRẮNG KHI CHƯA CÓ GÌ ĐỂ GIỮ. Xoá trắng mỗi lượt tải là mỗi lần đổi ngày màn
       lại chớp một cái rồi nhảy về đầu trang — anh Thắng gọi là *"trắng xíu trong nhảy ra"*. */
    if (!S.dsR) {
      o.innerHTML = '<div class="khung"><div class="trong">Đang tải…</div></div>';
    } else {
      o.setAttribute('aria-busy', 'true');
    }
    api('doi-soat?tu=' + k.tu + '&den=' + k.den + '&cua_hang=' + encodeURIComponent(S.ds.ch))
      .then(function (r) {
        if (luot !== dsLuot) return;
        o.removeAttribute('aria-busy');
        veDoiSoat(o, r, k);
      })
      .catch(function (e) {
        if (luot !== dsLuot) return;
        o.removeAttribute('aria-busy');
        o.innerHTML = '<div class="khung"><div class="trong">' + esc(e.message || e) + '</div></div>';
      });
  }

  /* Hàng nút lọc của riêng tab Đối soát. */
  function locDoiSoat() {
    var d = S.ds, ch = (S.cf && S.cf.cua_hang) || [];
    var nut = [['1', 'Ngày mới nhất'], ['7', '7 ngày'], ['30', '30 ngày'], ['thang', 'Tháng này'], ['all', 'Tất cả']];
    return '<div class="loc" id="dsLoc" style="margin:14px 0 4px">' +
      nut.map(function (n) {
        return '<button class="vien" type="button" data-dsk="' + n[0] + '"' +
          (d.ky === n[0] ? ' aria-pressed="true"' : '') + '>' + esc(n[1]) + '</button>';
      }).join('') +
      '<span class="day"></span>' +
      (ch.length > 1
        ? '<span class="o"><label for="dsCH">Cơ sở</label><select id="dsCH">' +
          '<option value="*">Tất cả cơ sở</option>' +
          ch.map(function (t) {
            return '<option value="' + esc(t) + '"' + (d.ch === t ? ' selected' : '') + '>' + esc(t) + '</option>';
          }).join('') + '</select></span>'
        : '') +
      '<span class="o"><label for="dsTu">Từ</label><input type="date" id="dsTu" value="' + esc(d.tu || '') + '">' +
        '<label for="dsDen">đến</label><input type="date" id="dsDen" value="' + esc(d.den || '') + '">' +
        '<button class="nut" type="button" id="dsLoc" title="Chạy theo hai ngày đã chọn">Lọc</button></span>' +
      '<span class="o"><label for="dsCanh"><input type="checkbox" id="dsCanh"' + (d.chiCanh ? ' checked' : '') +
        '> chỉ dòng cần xem</label></span>' +
    '</div>';
  }

  function noiLocDoiSoat(o) {
    Array.prototype.forEach.call(o.querySelectorAll('[data-dsk]'), function (b) {
      b.addEventListener('click', function () { S.ds.ky = b.dataset.dsk; taiDoiSoat(); });
    });
    var t = o.querySelector('#dsTu'), d = o.querySelector('#dsDen'),
        c = o.querySelector('#dsCH'), k = o.querySelector('#dsCanh');
    noiONgay(t, function (v) { S.ds.ky = 'tay'; S.ds.tu = v; taiDoiSoat(); });
    noiONgay(d, function (v) { S.ds.ky = 'tay'; S.ds.den = v; taiDoiSoat(); });
    var nl = o.querySelector('#dsLoc');
    if (nl) nl.addEventListener('click', function () {
      locTay(t, d, function (tu, den) { S.ds.ky = 'tay'; S.ds.tu = tu; S.ds.den = den; taiDoiSoat(); });
    });
    locKhiEnter(t, nl); locKhiEnter(d, nl);
    if (c) c.addEventListener('change', function () { S.ds.ch = c.value; taiDoiSoat(); });
    /* Lọc "chỉ dòng cần xem" vẽ lại tại chỗ, không hỏi lại máy chủ — cùng một bộ số. */
    if (k) k.addEventListener('change', function () { S.ds.chiCanh = k.checked; veDoiSoat(q('#dtTabDoiSoat'), S.dsR, S.dsK); });
  }

  /* ═══ PHÂN TRANG DÙNG CHUNG ═══════════════════════════════════════════════════════════
     Anh Thắng 18/09/2026: *"hiện 10 giao dịch cho 1 trang cho gọn nhé"*, rồi *"trang này cũng
     vậy"* cho bảng đối soát cơ sở. Hai chỗ, một bộ — viết hai bản là sớm muộn một bên đổi số
     dòng mỗi trang mà bên kia không đổi.

     ⚠️ SỐ TRANG NHỚ THEO TỪNG BẢNG. Ba bảng lệch giao dịch nằm cùng một màn; dùng chung một ô
        nhớ thì bấm sang trang 3 ở bảng này là hai bảng kia cũng nhảy sang trang 3 — mà chúng
        thường không dài bằng nhau, nên hai bảng kia sẽ trống trơn.

     ⚠️ KẸP LẠI TRONG KHOẢNG HỢP LỆ mỗi lần cắt. Đang ở trang 9 rồi đổi kỳ sang một khoảng chỉ
        có 2 trang thì không kẹp là màn trắng, mà người dùng không hiểu vì sao — trông y như mất
        dữ liệu. */
  var MOI_TRANG = 20;

  function catTrang(khoa, ds) {
    var so = Math.max(1, Math.ceil(ds.length / MOI_TRANG));
    var t  = Math.min(Math.max(1, (S.trang && S.trang[khoa]) || 1), so);
    return { dong: ds.slice((t - 1) * MOI_TRANG, t * MOI_TRANG), trang: t, so_trang: so, tong: ds.length };
  }

  function thanhTrang(khoa, p) {
    if (p.so_trang <= 1) return '';
    var nut = function (t, chu, tat) {
      return '<button class="vien" type="button" data-trang="' + esc(khoa) + '" data-so="' + t + '"' +
        (tat ? ' disabled' : '') + '>' + chu + '</button>';
    };
    return '<div class="loc" style="margin-top:8px;justify-content:flex-end;align-items:center">' +
      '<span class="nho">' + nguyen(p.tong) + ' dòng · trang ' + p.trang + '/' + p.so_trang + '</span>' +
      nut(p.trang - 1, '← Trước', p.trang <= 1) +
      nut(p.trang + 1, 'Sau →', p.trang >= p.so_trang) +
      '</div>';
  }

  function veDoiSoat(o, r, k) {
    /* Giữ lại bộ số và kỳ vừa tải, để nút "chỉ dòng cần xem" vẽ lại được mà không gọi lại máy chủ. */
    S.dsR = r; S.dsK = k;
    var ng = r.nguong || { phan_tram: 2, so_tien: 500000 };
    var ds = r.dong || [];
    var chuaNhap = ds.filter(function (x) { return !x.co_bao_cao; }).length;
    var tongLech = 0, tongHuy = 0, soCanh = 0, tongLechNop = 0, soLechNop = 0;
    /* Câu hỏi chính của tab này: TIỀN ĐÃ VỀ TÀI KHOẢN CHƯA. Đếm nó trên MỌI dòng, kể cả dòng cơ
       sở chưa nhập báo cáo — máy POS và ngân hàng đủ trả lời, không phải chờ ai gõ gì. */
    /* Tiền đang treo = cộng dồn tiền mặt phải nộp trừ tiền đã về. Lấy DÒNG MỚI NHẤT của mỗi cơ
       sở, vì đó là số dư cuối kỳ — cộng mọi dòng lại là cộng cùng một khoản mấy chục lần. */
    var cuoi = {}, chuaKhai = {};
    ds.forEach(function (x) {
      if (!x.co_ma && !x.co_bank) { chuaKhai[x.cua_hang] = true; return; }
      if (!cuoi[x.cua_hang] || x.ngay > cuoi[x.cua_hang].ngay) cuoi[x.cua_hang] = x;
      if (!x.co_bao_cao) return;
      tongLech += x.lech_tm || 0; tongHuy += x.tien_huy || 0;
      if (x.lech_nop) { tongLechNop += x.lech_nop; soLechNop++; }
      if (canhBao(x, ng, r.ngay_nhac)) soCanh++;
    });
    var nhac = r.ngay_nhac || 10;
    var soXong = 0, soCanNop = 0;
    ds.forEach(function (x) {
      if (!x.phai_nop || x.phai_nop <= 0) return;
      if (!x.co_ma && !x.co_bank) return;
      soCanNop++;
      if (x.da_xong || (x.treo || 0) < 1000) soXong++;
    });
    var dangTreo = 0, soTreoLau = 0, treoLau = [];
    Object.keys(cuoi).forEach(function (c) {
      var x = cuoi[c];
      dangTreo += x.treo || 0;
      if ((x.treo || 0) > ng.so_tien && (x.ngay_treo || 0) >= nhac) {
        soTreoLau++; treoLau.push(c);
      }
    });
    var sk = r.sk_chua_gan || { so_dong: 0, so_tien: 0 };
    /* Lọc CHỈ ở phần bảng — mấy ô đếm phía trên vẫn tính trên cả kỳ, vì "còn bao nhiêu tiền chưa
       về" mà đổi theo bộ lọc thì nó không còn là con số để nhìn mỗi sáng nữa. */
    var hien = S.ds.chiCanh
      ? ds.filter(function (x) { return canhBao(x, ng, r.ngay_nhac) || !x.co_bao_cao; })
      : ds;

    var h = '<div class="khung"><header><h2>Đối soát cơ sở với máy POS</h2>' +
      '<span class="goi">' + ngayVN(k.tu) + ' → ' + ngayVN(k.den) + ' · ' + ds.length + ' ngày×cơ sở' +
      (S.ds.chiCanh ? ' · hiện ' + hien.length : '') + '</span></header>' +
      locDoiSoat() +
      '<div class="the-hang" style="margin:16px 0">' +
        the_nho('Ngày đã nộp đủ', soCanNop ? nguyen(soXong) + ' / ' + nguyen(soCanNop) : '—',
          (soCanNop && soXong < soCanNop) ? '' : '') +
        the_nho('Tiền mặt đang treo ở cơ sở', tien(dangTreo), dangTreo > ng.so_tien ? 'xau' : '') +
        the_nho('Cơ sở treo quá ' + nhac + ' ngày', nguyen(soTreoLau) +
          (treoLau.length ? ' · ' + String(treoLau[0]).slice(0, 18) + (treoLau.length > 1 ? '…' : '') : ''),
          soTreoLau ? 'xau' : '') +
        the_nho('Ngày chưa nhập báo cáo', nguyen(chuaNhap), chuaNhap ? 'xau' : '') +
        the_nho('Ngày vượt ngưỡng', nguyen(soCanh), soCanh ? 'xau' : '') +
        the_nho('Tổng tiền bill huỷ', tien(tongHuy), tongHuy > 0 ? 'xau' : '') +
        (r.co_bank
          ? the_nho('Khai nộp nhiều hơn ngân hàng nhận', tien(tongLechNop) +
              (soLechNop ? ' · ' + soLechNop + ' ngày' : ''), tongLechNop > 0 ? 'xau' : '')
          : '') +
      '</div>' +
      /* Dòng tiền chưa nhận mặt được cơ sở là "chưa nộp" GIẢ — nó tố oan người đã nộp thật, nên
         phải nằm ngay trên bảng chứ không nằm trong một tab nào đó. */
      (sk.so_dong
        ? '<div class="canh-ghep">Sao kê còn <b>' + nguyen(sk.so_dong) + ' dòng tiền vào</b> chưa ' +
          'nhận ra cơ sở nào — tổng <b>' + tien(sk.so_tien) + '</b>. Chừng nào chưa gán thì mấy ' +
          'khoản ấy vẫn bị tính là chưa nộp. Vào tab <b>Quản trị → Sao kê ngân hàng</b> để khai.</div>'
        : '') +
      (!r.co_bank
        ? '<div class="canh-ghep">Chưa nạp sao kê ngân hàng nên cột <b>Thực nộp</b> đang lấy số ' +
          'cơ sở tự khai — số ấy không kiểm được gì. Bấm <b>Nạp báo cáo → Sao kê ngân hàng</b>.</div>'
        : '') +
      (r.co_bank && r.nguon_bank && r.nguon_bank.bang
        ? '<div class="chu-them" style="margin:8px 0">Cột <b>Ngân hàng nhận</b> đang đọc sổ ' +
          '<code class="nd">' + esc(r.nguon_bank.bang) + '</code> — ' + nguyen(r.nguon_bank.so_dong) +
          ' khoản' + (r.nguon_bank.tu_ngay ? ', từ ' + ngayVN(r.nguon_bank.tu_ngay) + ' đến ' +
            ngayVN(r.nguon_bank.den_ngay) : '') + '. Nếu đó không phải sao kê ngân hàng thì mọi ' +
          'kết luận "chưa nộp" bên dưới đều sai — đổi sổ ở <b>Quản trị → Sao kê ngân hàng</b>.</div>'
        : '') +
      (Object.keys(chuaKhai).length
        ? '<div class="canh-ghep"><b>' + Object.keys(chuaKhai).length + ' cơ sở chưa khai mã nộp tiền</b> — ' +
          'hệ chưa biết tiền của họ đã về hay chưa, nên không tính vào ô "chưa về tài khoản" và ' +
          'không nêu tên ai. Khai ở <b>Quản trị → Sao kê ngân hàng</b>: ' +
          esc(Object.keys(chuaKhai).map(function (t) { return String(t).slice(0, 26); }).join(' · ')) +
          '</div>'
        : '') +
      '<div class="bang-cuon"><table><thead><tr>' +
        '<th>Ngày</th><th>Cơ sở</th><th>POS</th><th>Sale vé</th><th>Bán lẻ</th><th>Sale phụ</th><th>Tiền mặt POS</th>' +
        '<th>Ngân hàng nhận</th><th>Đang treo</th>' +
        '<th>Đếm két</th><th>Lệch</th><th>Bill huỷ</th><th>Hàng bán</th><th>Khách − máy</th><th>Người nhập</th>' +
      '</tr></thead><tbody>';
    var ptDS = catTrang('doi_soat', hien);
    ptDS.dong.forEach(function (x) {
      var do_ = canhBao(x, ng, r.ngay_nhac);
      h += '<tr' + (do_ ? ' class="canh"' : '') + '>' +
        '<td>' + esc(ngayVN(x.ngay)) + '</td>' +
        '<td>' + esc(String(x.cua_hang).slice(0, 34)) + '</td>' +
        '<td class="s">' + tien(x.doanh_thu) + '</td>' +
        '<td class="s">' + tien(x.tien_ve || 0) + '</td>' +
        '<td class="s">' + tien(x.tien_le || 0) + '</td>' +
        '<td class="s">' + tien(x.tien_phu || 0) + '</td>' +
        '<td class="s">' + tien(x.pos_tm) + '</td>' +
        /* Hai cột tiền nộp luôn hiện — không nấp sau việc cơ sở đã nhập báo cáo hay chưa. */
        '<td class="s">' + (x.co_bank
          ? tien(x.nop_bank) +
            (x.nop_lan > 1 ? '<span class="nho"> ' + x.nop_lan + ' lần</span>' : '') +
            (x.lech_nop
              ? '<span class="nho xau" title="Cơ sở khai đã nộp ' + esc(tien(x.nop)) + '"> khai ' +
                (x.lech_nop > 0 ? '+' : '') + nguyen(x.lech_nop) + '</span>'
              : '')
          : '<span class="khai">—</span>') + '</td>' +
        '<td>' + theTreo(x, r.co_bank, ng, r.ngay_nhac || 10) + '</td>' +
        (x.co_bao_cao
          ? '<td class="s">' + tien(x.dem) + '</td>' +
            '<td class="s">' + (x.lech_tm ? (x.lech_tm > 0 ? '+' : '') + tien(x.lech_tm) : '0') + '</td>' +
            '<td class="s">' + (x.bill_huy ? nguyen(x.bill_huy) + ' · ' + tien(x.tien_huy) : '—') + '</td>' +
            /* Cơ sở chốt hàng bán: khớp máy, hay lệch món nào (kế toán xác nhận ở đây). */
            '<td class="s">' + (x.mon_lech
              ? (x.mon_lech.n
                  ? '<b style="color:var(--xau)" title="' + esc((x.mon_lech.ds || []).map(function (d) { return d.n + ': máy ' + nguyen(d.may) + ' / thực ' + nguyen(d.thuc); }).join('\n')) + '">' + x.mon_lech.n + ' món lệch</b>'
                  : (x.mon_lech.da_chot ? 'khớp máy' : '<span class="khai">chưa soát</span>'))
              : '—') + '</td>' +
            '<td class="s">' + (x.khach && (x.khach_may != null ? x.khach_may : x.so_ve)
              ? nguyen(x.khach - Math.round(x.khach_may != null ? x.khach_may : x.so_ve)) : '—') + '</td>' +
            '<td>' + esc(x.nguoi || '') + (x.chot ? ' ✓' : '') + '</td>'
          : '<td colspan="6" class="chua">chưa nhập báo cáo ngày</td>') +
        '</tr>';
    });
    if (!hien.length) {
      h += '<tr><td colspan="11" class="chua">' +
        (ds.length ? 'Kỳ này không có dòng nào cần xem — mọi khoản đã về tài khoản.'
          : 'Kỳ này chưa có số liệu POS nào.') + '</td></tr>';
    }
    h += '</tbody></table></div>' +
      '<div class="chu-them">Bôi đỏ khi lệch tiền mặt hoặc phần chưa nộp vượt ' +
        phan(ng.phan_tram) + ' doanh thu ngày, hoặc vượt ' + tien(ng.so_tien) + '. ' +
        (r.co_bank
          ? 'Cột <b>Ngân hàng nhận</b> là tiền thật sự về tài khoản theo sao kê. Cột <b>Đang treo</b> ' +
            'là <b>cộng dồn</b> tiền mặt phải nộp trừ tiền đã về — vì cơ sở gom mấy ngày nộp một cục, ' +
            'nên "hôm nay không có giao dịch" là bình thường. Chỉ bôi đỏ khi treo quá ' +
            (r.ngay_nhac || 10) + ' ngày và quá ' + tien(ng.so_tien) + '.'
          : 'Chưa nạp sao kê nên chưa biết tiền đã về tài khoản hay chưa.') +
        '</div></div>';
    /* Tổng hợp theo cơ sở nằm ngay dưới bảng ngày: nhìn từng ngày xong thì hỏi "cả kỳ thì sao". */
    h += veTongHop(ds, k);

    h += veMomo(ds, k);
    /* Bảng lệch TỪNG GIAO DỊCH nạp riêng, vì nó đọc hai sổ giao dịch chứ không dùng lại bộ số
       của bảng ngày. */
    h += '<div class="khung" id="dtMomoGd"><header><h2>Lệch giao dịch MoMo</h2></header>' +
      '<div id="dtMomoGdNoi"><div class="trong">Đang tải…</div></div></div>';

    /* Khối lịch nằm DƯỚI mấy ô đếm và TRÊN bảng ngày: nhìn hình dạng cả tháng trước, rồi mới
       soi từng ngày. */
    h += '<div class="khung" id="dtLich"><header><h2>Lịch nộp tiền</h2>' +
      '<span class="goi"><label for="dsThang">Tháng</label> ' +
      '<input type="month" id="dsThang" value="' + esc(S.lich.thang || thangCua(k.den)) + '"></span></header>' +
      '<div id="dsLichNoi"><div class="trong">Đang tải…</div></div></div>';

    o.innerHTML = h;
    noiLocDoiSoat(o);
    taiLich(o, S.lich.thang || thangCua(k.den));
    taiMomoGd(o, k);
  }

  /**
   * TỔNG HỢP CẢ KỲ THEO CƠ SỞ — trả lời câu "tổng nộp có lệch so với doanh thu không".
   *
   * 🔴 TIỀN NỘP KHÔNG SO VỚI TỔNG DOANH THU, MÀ SO VỚI TIỀN MẶT.
   *    Anh Thắng 16/09/2026 hỏi đúng câu ấy. Nhưng doanh thu gồm cả phần khách trả bằng chuyển
   *    khoản và quét QR — phần đó TỰ về tài khoản, không ai mang đi nộp. Đem tiền nộp so với
   *    tổng doanh thu thì quán nào cũng "thiếu" đúng bằng phần QR, tháng nào cũng thiếu, và con
   *    số ấy không nói lên điều gì.
   *
   *    Phép đúng là: tiền mặt máy POS = tiền đã nộp + phần còn treo. Lệch ra ngoài hai thứ đó
   *    mới là tiền biến mất.
   */
  function veTongHop(ds, k) {
    var theo = {}, ten = [];
    ds.forEach(function (x) {
      var c = x.cua_hang;
      if (!theo[c]) {
        theo[c] = { dt: 0, tm: 0, ck: 0, nop: 0, momo: 0, pmomo: 0, dau: null, cuoi: null,
                    ngayDau: '', ngayCuoi: '', gop: 0 };
        ten.push(c);
      }
      var t = theo[c];
      t.dt += x.doanh_thu || 0;
      t.tm += x.phai_nop || 0;
      t.ck += x.pos_ck || 0;
      t.nop += x.nop_bank || 0;
      t.pmomo += x.pos_momo || 0;
      t.momo += (S.dsR && S.dsR.momo ? (S.dsR.momo[x.ngay + '|' + c] || 0) : 0);
      /* Đếm những ô lấy từ sổ gộp — chúng cho tổng đúng, nhưng không tra xuống giao dịch được. */
      if (S.dsR && S.dsR.momo_nguon_o && S.dsR.momo_nguon_o[x.ngay + '|' + c] === 'gop') t.gop++;
      if (!t.ngayCuoi || x.ngay > t.ngayCuoi) { t.ngayCuoi = x.ngay; t.cuoi = x.treo || 0; }
      if (!t.ngayDau || x.ngay < t.ngayDau) {
        t.ngayDau = x.ngay;
        /* Treo đầu kỳ = treo sau ngày đầu tiên, trừ đi phần chính ngày ấy góp vào. */
        t.dau = (x.treo || 0) - ((x.phai_nop || 0) - (x.nop_bank || 0));
        if (t.dau < 0) t.dau = 0;
      }
    });
    ten.sort();
    if (!ten.length) return '';
    var coMomo = !!(S.dsR && S.dsR.co_momo);

    var T = { dt: 0, tm: 0, ck: 0, nop: 0, momo: 0, pmomo: 0, dau: 0, cuoi: 0 };
    var hang = ten.map(function (c) {
      var t = theo[c];
      ['dt', 'tm', 'ck', 'nop', 'momo', 'pmomo', 'dau', 'cuoi'].forEach(function (f) { T[f] += t[f] || 0; });
      /* Tiền mặt vào trong kỳ phải bằng: đã nộp + (treo cuối − treo đầu). Lệch ra là con số
         không giải thích được — gần như luôn là do sao kê thiếu khoản, hoặc mã nộp tiền khai
         sót; nhưng phải bày ra chứ không được lặng lẽ làm tròn. */
      var lech = t.tm - (t.nop + (t.cuoi - t.dau));
      return { c: c, t: t, lech: lech };
    });

    var o_ = function (v) { return '<td class="s">' + tien(v) + '</td>'; };
    var h = '<div class="khung"><header><h2>Tổng hợp cả kỳ theo cơ sở</h2>' +
      '<span class="goi">' + ngayVN(k.tu) + ' → ' + ngayVN(k.den) + '</span></header>' +
      ((S.dsR && S.dsR.pos_som && S.dsR.nguon_bank && S.dsR.nguon_bank.tu_ngay &&
        S.dsR.nguon_bank.tu_ngay < S.dsR.pos_som)
        ? '<div class="canh-ghep">⚠️ <b>Hai sổ lệch kỳ nhau.</b> Sao kê có khoản từ ' +
          ngayVN(S.dsR.nguon_bank.tu_ngay) + ', còn kho POS mới có từ ' + ngayVN(S.dsR.pos_som) + '. ' +
          'Nên cột <b>Đã nộp</b> gánh cả tiền mặt của những ngày chưa có số POS — vì thế nó có thể ' +
          'lớn hơn cột Tiền mặt, và cột Không khớp ra số âm. Nạp file POS của kỳ trước vào là hết.</div>'
        : '') +
      '<div class="chu-them" style="margin-top:6px">Tiền nộp so với <b>tiền mặt</b>, không so với ' +
      'tổng doanh thu: phần khách trả bằng chuyển khoản và quét QR tự về tài khoản, không ai mang ' +
      'đi nộp. Phép đúng là <b>tiền mặt POS = đã nộp + còn treo</b>.</div>' +
      /* Dấu ◷ phải có chỗ giải nghĩa. Một ký hiệu không ai đọc được thì bằng không có. */
      (hang.some(function (r_) { return r_.t.gop > 0; })
        ? '<div class="chu-them" style="margin-top:4px"><b>◷</b> = số MoMo của ngày ấy lấy từ ' +
          '<b>sổ gộp</b> (tổng ngày đúng, nhưng không tra xuống từng giao dịch được). Muốn tra tới ' +
          'từng mã thì nạp file <code>Transaction_report_….csv</code> ở thẻ <b>Sao kê MoMo</b>.</div>'
        : '') +
      '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
        '<th style="text-align:left">Cơ sở</th><th>Doanh thu POS</th><th>CK / QR</th>' +
        (coMomo ? '<th>MoMo (POS)</th><th>MoMo (sao kê)</th><th>Lệch MoMo</th>' : '') +
        '<th>Tiền mặt</th><th>Đã nộp</th><th>Treo đầu kỳ</th><th>Treo cuối kỳ</th><th>Không khớp</th>' +
      '</tr></thead><tbody>' +
      hang.map(function (r_) {
        var t = r_.t;
        return '<tr><td style="text-align:left">' + esc(String(r_.c).slice(0, 34)) + '</td>' +
          o_(t.dt) + o_(t.ck) +
          (coMomo ? o_(t.pmomo) +
            '<td class="s">' + tien(t.momo) +
            (t.gop ? ' <span class="nho" title="' + t.gop + ' ngày lấy từ sổ gộp — chỉ có tổng ' +
              'ngày, không tra xuống từng giao dịch được">◷</span>' : '') + '</td>' +
            '<td class="s">' + (Math.abs(t.pmomo - t.momo) < 1000
            ? '<span style="color:var(--tot)">0</span>'
            : '<b style="color:var(--s4)">' + (t.pmomo - t.momo > 0 ? '+' : '') + nguyen(t.pmomo - t.momo) + '</b>')
            + '</td>' : '') +
          o_(t.tm) + o_(t.nop) + o_(t.dau) + o_(t.cuoi) +
          '<td class="s">' + (Math.abs(r_.lech) < 1000 ? '<span style="color:var(--tot)">0</span>'
            : '<b style="color:var(--xau)">' + (r_.lech > 0 ? '+' : '') + nguyen(r_.lech) + '</b>') + '</td></tr>';
      }).join('') +
      '<tr style="font-weight:700;border-top:2px solid var(--line-2)">' +
        '<td style="text-align:left">Tất cả ' + ten.length + ' cơ sở</td>' +
        o_(T.dt) + o_(T.ck) +
        (coMomo ? o_(T.pmomo) + o_(T.momo) + '<td class="s">' + nguyen(T.pmomo - T.momo) + '</td>' : '') +
        o_(T.tm) + o_(T.nop) + o_(T.dau) + o_(T.cuoi) +
        '<td class="s">' + nguyen(T.tm - (T.nop + (T.cuoi - T.dau))) + '</td></tr>' +
      '</tbody></table></div>' +
      '<div class="chu-them" style="margin-top:10px"><b>Cách đọc:</b> cột <b>Treo cuối kỳ</b> là ' +
      'tiền mặt cơ sở đang giữ chưa nộp — đó mới là con số phải hỏi, và phải hỏi khi nó CỨ LỚN DẦN ' +
      'qua từng tháng. Cột <b>Không khớp</b> gần như luôn bằng 0; khác 0 thường là do sao kê thiếu ' +
      'khoản, mã nộp tiền khai sót, hoặc có kỳ nộp dư (phần dư không mang sang kỳ sau). Nó là dấu ' +
      'hiệu <b>số liệu chưa đủ</b>, không phải dấu hiệu ai lấy tiền.</div></div>';
    return h;
  }

  /**
   * ĐỐI SOÁT MOMO — ba con số cho cùng một đồng tiền.
   *
   *   máy POS ghi khách trả qua MoMo  →  sao kê MoMo ghi MoMo nhận  →  ngân hàng ghi MoMo chuyển về
   *
   * Mỗi chỗ lệch là một câu hỏi KHÁC NHAU, nên không được gộp thành một con số "chênh lệch":
   *   POS > sao kê MoMo   — có giao dịch ghi trên máy mà MoMo không nhận (bấm nhầm hình thức,
   *                         hoặc đơn huỷ mà máy vẫn ghi).
   *   sao kê MoMo > POS   — MoMo nhận tiền mà máy không ghi: thu ngoài sổ.
   *
   * ⚠️ NGÀY CHƯA TẢI FILE KHÔNG PHẢI NGÀY BẰNG 0. MoMo không bắn webhook nên sổ ấy do người ta
   *    tải file lên hằng ngày. Đếm ngày thiếu file RIÊNG, và trừ hẳn những ngày ấy ra khỏi phép
   *    so — không thì mỗi ngày quên tải file lại hoá thành một lời tố "MoMo giữ tiền".
   */
  function veMomo(ds, k) {
    if (!(S.dsR && S.dsR.co_momo)) return '';
    var co = {}; (S.dsR.momo_ngay_co || []).forEach(function (n) { co[n] = true; });
    var sk = S.dsR.momo || {};
    /* Phí MoMo đã được máy chủ chia sẵn về từng cơ sở (theo % doanh thu, trong phạm vi của
       chính lượt nhập). Ở đây chỉ việc bày ra — KHÔNG chia lại, vì chia lại theo khoảng đang
       xem thì cùng một lượt phí sẽ ra số khác nhau tuỳ người đang nhìn kỳ nào. */
    var phiCS = (S.dsR.momo_phi && S.dsR.momo_phi.co_so) || {};
    var phiThieu = S.dsR.momo_phi_thieu || {};
    var theo = {}, ten = [], ngayThieu = {};
    ds.forEach(function (x) {
      if (!theo[x.cua_hang]) { theo[x.cua_hang] = { pos: 0, mm: 0, ngay: 0, bo: 0 }; ten.push(x.cua_hang); }
      var t = theo[x.cua_hang];
      if (!co[x.ngay]) {
        /* Sổ MoMo chưa có ngày này — bỏ hẳn khỏi phép so, và đếm lại để nói ra. */
        if (x.pos_momo > 0) { t.bo++; ngayThieu[x.ngay] = true; }
        return;
      }
      t.pos += x.pos_momo || 0;
      t.mm += sk[x.ngay + '|' + x.cua_hang] || 0;
      t.ngay++;
    });
    ten.sort();

    /* ═══ TÁCH RIÊNG "CHƯA CÓ SỔ CHO CƠ SỞ NÀY" ═══════════════════════════════════════════
       K&H có HAI pháp nhân MoMo, mỗi pháp nhân một bản Transaction report riêng. Nạp bên này
       mà chưa nạp bên kia là chuyện thường ngày, không phải sự cố.

       Trước 17/09/2026 mấy cơ sở của pháp nhân chưa nạp bị kể thẳng vào ô Lệch: bảng báo
       98.160.000đ "máy POS ghi mà MoMo không nhận" trong khi không mất một đồng nào. Kế toán
       đọc con số ấy là đi tìm gần trăm triệu không hề thất lạc — và lần sau sẽ không tin bảng
       này nữa, kể cả lúc nó báo đúng.

       ⚠️ CHỖ HỎNG LÀ `co[x.ngay]`: nó hỏi "sổ có NGÀY này không", một cờ chung cho cả hệ. Sổ
          của pháp nhân A phủ đủ ngày, nên mọi ngày đều tính là "có sổ" — kể cả với cơ sở của
          pháp nhân B mà sổ ấy không hề nhắc tới. Vì thế cột "Ngày thiếu file" đứng 0 trong khi
          cơ sở ấy không có lấy một dòng sổ.

       ⚠️ PHÂN ĐỊNH BẰNG "SỔ CÓ NHẮC TỚI CƠ SỞ NÀY KHÔNG", KHÔNG PHẢI "tiền sổ có bằng 0 không".
          Hai ca ấy khác hẳn nhau: sổ KHÔNG PHỦ cơ sở thì không kết luận được gì; sổ CÓ PHỦ mà
          bằng 0 thì đó là lệch thật — máy ghi có mà MoMo không nhận — và phải kêu. Lấy khoá
          'ngay|cơ sở' của chính sổ mà suy ra, nên không cần máy chủ gửi thêm gì. */
    var coSoTrongSo = {};
    Object.keys(sk).forEach(function (kk) {
      var i = kk.indexOf('|');
      if (i > 0) coSoTrongSo[kk.slice(i + 1)] = true;
    });
    var chuaSo = [];
    ten = ten.filter(function (c) {
      /* Cơ sở không có đồng MoMo nào bên POS thì để yên trong bảng chính (0/0/0) — nó không
         thiếu sổ, nó chỉ không bán được gì qua MoMo. */
      if (coSoTrongSo[c] || !(theo[c].pos > 0)) return true;
      chuaSo.push(c);
      return false;
    });
    if (!ten.length && !chuaSo.length) return '';

    var TP = 0, TM = 0, TF = 0;
    var hang = ten.map(function (c) {
      var t = theo[c]; TP += t.pos; TM += t.mm;
      var f = phiCS[c] || 0; TF += f;
      return '<tr><td style="text-align:left">' + esc(String(c).slice(0, 34)) + '</td>' +
        '<td class="s">' + tien(t.pos) + '</td><td class="s">' + tien(t.mm) + '</td>' +
        /* Phí và Thực nhận. Ô nào chưa có phí thì để dấu — chứ KHÔNG in 0đ: 0 nghĩa là "MoMo
           không thu phí", còn đây là "chưa ai nhập" — hai chuyện khác hẳn nhau. */
        '<td class="s">' + (f ? tien(f) : '<span style="color:var(--ink-3)">—</span>') + '</td>' +
        '<td class="s">' + (f ? tien(t.mm - f) : '<span style="color:var(--ink-3)">—</span>') + '</td>' +
        '<td class="s">' + (Math.abs(t.pos - t.mm) < 1000
          ? '<span style="color:var(--tot)">0</span>'
          : '<b style="color:var(--xau)">' + (t.pos - t.mm > 0 ? '+' : '') + nguyen(t.pos - t.mm) + '</b>') + '</td>' +
        '<td class="s">' + nguyen(t.ngay) + '</td>' +
        '<td class="s">' + (t.bo ? '<b style="color:var(--s4)">' + nguyen(t.bo) + '</b>' : '0') + '</td></tr>';
    }).join('');

    var thieu = Object.keys(ngayThieu).sort();

    /* Nhắc ngày CÓ doanh thu MoMo mà chưa ai nhập phí, kèm ô nhập ngay tại chỗ.
       ⚠️ Khác hẳn khối "thiếu file" ở trên: thiếu file là chưa có SỐ LIỆU nên không so được;
          thiếu phí là số liệu có đủ, chỉ chưa biết MoMo trừ bao nhiêu. Gộp hai câu làm một thì
          người đọc không biết phải đi tải file hay đi tra màn đối soát bên MoMo. */
    var TC = 0;
    chuaSo.forEach(function (c) { TC += theo[c].pos; });
    var khoiChuaSo = !chuaSo.length ? '' :
      '<div class="canh-ghep" style="margin-top:12px">' +
      '<b>' + chuaSo.length + ' cơ sở chưa có sổ MoMo</b> — sổ đang nạp không chứa giao dịch nào ' +
      'của mấy cơ sở này, nên <b>KHÔNG kể là lệch</b> và không cộng vào bảng trên. Chưa có sổ thì ' +
      'chưa kết luận được gì, khác hẳn với "MoMo không nhận tiền".<br>' +
      'K&amp;H có <b>hai pháp nhân MoMo</b>, mỗi pháp nhân một bản Transaction report riêng — ' +
      'nhiều khả năng đây là mấy cơ sở thuộc bản còn lại.' +
      '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
        '<th style="text-align:left">Cơ sở</th><th>MoMo trên máy POS</th>' +
      '</tr></thead><tbody>' +
      chuaSo.map(function (c) {
        return '<tr><td style="text-align:left">' + esc(String(c).slice(0, 34)) + '</td>' +
          '<td class="s">' + tien(theo[c].pos) + '</td></tr>';
      }).join('') +
      '<tr style="font-weight:700;border-top:2px solid var(--line-2)">' +
        '<td style="text-align:left">Cộng</td><td class="s">' + tien(TC) + '</td></tr>' +
      '</tbody></table></div>' +
      '<div style="margin-top:8px">' + NUT_NAP_MOMO_SK + '</div></div>';

    /* ═══ KHỐI NHẬP PHÍ — LÚC NÀO CŨNG MỞ ĐƯỢC ═══════════════════════════════════════════
       Bản 1.38.0 chỉ hiện ô nhập khi hệ biết ngày nào còn thiếu phí; mà muốn biết thì phải nạp
       lại sao kê kèm mã tài khoản trước. Anh Thắng cài xong hỏi *"Nhập phí chỗ nào theo KH785
       và KH989 chỗ nào"* — đúng: tính năng có mà không có cửa vào. Nay khối này luôn có mặt cho
       người được ghi, và tự điền sẵn khoảng ngày đang xem. */
    var phiDs = S.dsR.momo_phi_ds || [];
    var tkDs  = S.dsR.momo_tk_ds || [];      // để gợi ý ô gõ
    /* CHỈ tài khoản đã ghép được cơ sở. Dùng `tkDs` để quyết định cảnh báo là lỗi của 1.39.0:
       nó có cả tài khoản mới chỉ xuất hiện trong lượt nhập phí, nên màn báo "đã biết KH785"
       trong khi bảng ghép còn rỗng và phí không chia được. */
    var tkGhep = S.dsR.momo_tk_ghep || [];
    var chuaChia = (S.dsR.momo_phi && S.dsR.momo_phi.chua_chia) || [];
    var chiaTam  = (S.dsR.momo_phi && S.dsR.momo_phi.chia_tam) || [];
    var khoiPhi = '';
    if (S.cf && S.cf.duoc_ghi) {
      var tkThieu = Object.keys(phiThieu);
      var nhac = tkThieu.map(function (tk) {
        var ng = phiThieu[tk] || [];
        if (!ng.length) return '';
        return '<div style="margin-top:6px">Tài khoản <b>' + esc(tk) + '</b> chưa nhập phí cho <b>' +
          ng.length + ' ngày</b>: ' + esc(ng.map(ngayVN).join(' · ')) + '.</div>';
      }).join('');

      var daNhap = !phiDs.length ? '' :
        '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          '<th style="text-align:left">Tài khoản</th><th style="text-align:left">Khoảng ngày</th>' +
          '<th>Phí</th><th></th></tr></thead><tbody>' +
        phiDs.map(function (x) {
          return '<tr><td style="text-align:left"><b>' + esc(x.tai_khoan) + '</b></td>' +
            '<td style="text-align:left">' + esc(ngayVN(x.tu)) + ' → ' + esc(ngayVN(x.den)) + '</td>' +
            '<td class="s">' + tien(x.phi) + '</td>' +
            /* ⚠️ `data-phi-so` là số TRẦN ("62447"), không phải "62.447". Ô nhập nay là ô chữ
               và máy chủ đọc được cả hai, nhưng đổ số đã chấm phẩy vào rồi lưu lại là một
               vòng đọc-ghi thừa, chỉ chực sai. */
            '<td style="white-space:nowrap">' +
              '<button class="vien" type="button" data-phi-sua="1" data-phi-tk="' + esc(x.tai_khoan) +
              '" data-phi-tu="' + esc(x.tu) + '" data-phi-den="' + esc(x.den) +
              '" data-phi-so="' + esc(String(Math.round(Number(x.phi) || 0))) + '">Sửa</button> ' +
              '<button class="vien" type="button" data-phi-xoa="' + esc(x.id) + '">Xoá</button>' +
            '</td></tr>';
        }).join('') + '</tbody></table></div>';

      /* ═══ BẢNG GHÉP CƠ SỞ VÀO TÀI KHOẢN ═════════════════════════════════════════════════
         Anh Thắng 18/09/2026: *"Phí của 2 MoMo khác nhau mà"*, *"sau lại chia chung rồi"* —
         đúng. Chia tạm (gộp mọi cơ sở chưa có chủ vào một rổ) chỉ đỡ được lúc cả hệ mới có
         một tài khoản; K&H có hai pháp nhân nên nó lấy phí của KH785 rắc sang cả cơ sở của
         KH989. Muốn đúng thì phải có chỗ NÓI cơ sở nào thuộc tài khoản nào — mà không bắt đi
         nạp lại sao kê cả tháng, vì bảng ghép trước nay chỉ học được từ lượt nạp có gõ mã.

         Mở sẵn khi còn cơ sở chưa ghép: đó là việc đang dở, không phải mục nâng cao. */
      var maChDs  = S.dsR.momo_ma_ch_ds || [];
      var chuaAi  = maChDs.filter(function (x) { return !x.tai_khoan; }).length;
      var khoiGhep = !maChDs.length ? '' :
        '<details class="hop-ghep" style="margin-top:10px"' + (chuaAi ? ' open' : '') + '>' +
        '<summary style="cursor:pointer"><b>Ghép cơ sở vào tài khoản MoMo</b> — ' +
        (chuaAi ? '<b>còn ' + chuaAi + '/' + maChDs.length + ' cơ sở chưa ghép</b>'
                : 'đã ghép đủ ' + maChDs.length + ' cơ sở') + '</summary>' +
        '<div class="chu-them" style="margin-top:6px">Gõ mã tài khoản quyết toán của từng cơ sở ' +
        '(ví dụ KH785, KH989) rồi bấm Lưu ghép. Ghép xong thì phí của tài khoản nào chỉ chia cho ' +
        'cơ sở của tài khoản ấy. Để trống là bỏ ghép.</div>' +
        '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          '<th style="text-align:left">Cơ sở</th><th style="text-align:left">Mã cửa hàng</th>' +
          '<th>Doanh thu MoMo trong kỳ</th><th style="text-align:left">Tài khoản</th>' +
        '</tr></thead><tbody>' +
        maChDs.map(function (x) {
          return '<tr><td style="text-align:left">' + esc(x.co_so || x.ten_ch || '—') + '</td>' +
            '<td style="text-align:left" class="s">' + esc(x.ma_ch) + '</td>' +
            '<td class="s">' + tien(x.tien) + '</td>' +
            '<td style="text-align:left"><input type="text" list="dtTkGoi" data-ghep="' +
              esc(x.ma_ch) + '" value="' + esc(x.tai_khoan || '') + '" placeholder="KH785" ' +
              'style="width:100px"></td></tr>';
        }).join('') + '</tbody></table></div>' +
        '<div style="margin-top:8px"><button class="nut" type="button" data-ghep-luu="1">Lưu ghép</button></div>' +
        '</details>';

      /* Không bọc `.khung` — khối này nằm BÊN TRONG khung Đối soát MoMo, lồng khung vào khung
         là hai lớp viền và hai lớp nền chồng nhau. */
      khoiPhi = '<div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line)">' +
        '<h3 class="tieu-nho">Phí MoMo</h3>' +
        '<div class="chu-them">MoMo <b>không</b> đưa phí theo từng giao dịch — cả Transaction report ' +
        'lẫn báo cáo chi tiết đều không có. Chỉ màn <b>Đối soát</b> bên MoMo mới có, ở ô ' +
        '<b>"Số tiền điều chỉnh"</b>, và là một số tổng. Nhập số ấy vào đây (gộp 2-3 ngày cũng được), ' +
        'hệ chia về từng cơ sở theo <b>% doanh thu</b>.' +
        (tkGhep.length
          ? ' Tài khoản đã ghép được cơ sở: <b>' + tkGhep.map(esc).join('</b> · <b>') + '</b>.'
          : ' <b>Chưa cơ sở nào được ghép vào tài khoản nào</b> — mở bảng "Ghép cơ sở vào tài ' +
            'khoản MoMo" dưới đây mà ghép, hoặc nạp lại sao kê MoMo có gõ mã tài khoản (thẻ Sao kê ' +
            'MoMo).') +
        '</div>' +
        /* 🔴 Phí đã nhập mà chưa chia được thì PHẢI nói ra. Im lặng ở đây là anh Thắng gõ tiền
           vào rồi nhìn cột Phí trống trơn, không có gì để lần ra nguyên nhân. */
        (chuaChia.length
          ? '<div class="canh-ghep" style="margin-top:8px"><b>Đã nhập phí nhưng CHƯA chia được:</b> ' +
            chuaChia.map(function (x) {
              return esc(x.tai_khoan) + ' ' + esc(ngayVN(x.tu)) + '→' + esc(ngayVN(x.den)) +
                ' · ' + tien(x.phi);
            }).join(' · ') +
            '.<br>Hoặc chưa cơ sở nào được ghép vào tài khoản ấy (nạp lại sao kê MoMo có gõ mã tài ' +
            'khoản), hoặc khoảng ngày ấy không có giao dịch MoMo nào để chia.</div>'
          : '') +
        /* Chia tạm thì PHẢI nói là tạm. Con số trong cột Phí lúc này là phỏng đoán "cơ sở nào
           chưa có chủ thì chắc của tài khoản này" — đúng khi cả hệ mới có một tài khoản, mà
           chẳng còn đúng ngay khi tài khoản thứ hai có phí. Im lặng là để lại một cột số trông
           y hệt số đã ghép đàng hoàng. */
        (chiaTam.length
          ? '<div class="canh-ghep" style="margin-top:8px"><b>Đang chia TẠM:</b> ' +
            chiaTam.map(function (x) {
              return esc(x.tai_khoan) + ' ' + esc(ngayVN(x.tu)) + '→' + esc(ngayVN(x.den)) +
                ' · ' + tien(x.phi) + ' chia cho ' + (x.so_co_so || 0) + ' cơ sở';
            }).join(' · ') +
            '.<br>Tài khoản này chưa ghép được cơ sở nào, nên hệ tạm chia cho <b>mọi cơ sở chưa ' +
            'thuộc tài khoản nào khác</b> — theo % doanh thu MoMo, y như lúc đã ghép. ' +
            '🔴 <b>Phí hai tài khoản MoMo khác nhau</b>, nên chia chung thế này là cơ sở bên này ' +
            'gánh phí bên kia. Mở <b>"Ghép cơ sở vào tài khoản MoMo"</b> ngay dưới đây, gõ mã tài ' +
            'khoản cho từng cơ sở rồi Lưu ghép — xong là phí về đúng pháp nhân.</div>'
          : '') + nhac +
        khoiGhep +
        '<div class="loc" style="margin-top:10px;gap:6px">' +
          '<label class="o">Tài khoản<input type="text" data-phi="tk" list="dtTkGoi" placeholder="KH785" style="width:110px"></label>' +
          '<datalist id="dtTkGoi">' + tkDs.map(function (t) { return '<option value="' + esc(t) + '">'; }).join('') + '</datalist>' +
          '<label class="o">Từ<input type="date" data-phi="tu" value="' + esc(k.tu) + '"></label>' +
          '<label class="o">Đến<input type="date" data-phi="den" value="' + esc(k.den) + '"></label>' +
          /* ⚠️ type="text", KHÔNG phải "number". Màn Đối soát của MoMo in "68.866" và người ta
             chép y như thế — mà ô number hiểu dấu chấm là dấu THẬP PHÂN, nên 68.866 vào sổ
             thành 69đ. Máy chủ đọc bằng `khh_dt_so()`, hiểu cả dấu chấm lẫn dấu phẩy. */
          '<label class="o">Phí<input type="text" inputmode="numeric" data-phi="so" ' +
            'placeholder="68.866" style="width:120px"></label>' +
          '<button class="nut chinh" type="button" data-phi-luu="1">Lưu phí</button>' +
        '</div>' + daNhap + '</div>';
    }

    return '<div class="khung"><header><h2>Đối soát MoMo</h2>' +
      '<span class="goi">' + ngayVN(k.tu) + ' → ' + ngayVN(k.den) + '</span></header>' +
      '<div class="chu-them" style="margin-top:6px">So <b>đúng phần MoMo</b> máy POS ghi với sổ ' +
      'sao kê MoMo — không so với cả cục CK/QR, vì cục ấy còn có chuyển khoản, VNPAY và Việt QR.' +
      '</div>' +
      (thieu.length
        ? '<div class="canh-ghep">Sổ MoMo <b>chưa có ' + thieu.length + ' ngày</b> mà máy POS lại có ' +
          'doanh thu MoMo: ' + esc(thieu.map(ngayVN).join(' · ')) + '. Mấy ngày ấy đã được <b>bỏ ra ' +
          'khỏi phép so</b> — thiếu file khác hẳn với MoMo giữ tiền. Tải file MoMo của những ngày ' +
          'này lên rồi xem lại.' +
          '<div style="margin-top:8px">' + NUT_NAP_MOMO_SK + '</div></div>'
        : '') +
      /* Không cơ sở nào so được thì ĐỪNG vẽ bảng rỗng với dòng tổng 0 — một bảng toàn số 0
         trông y như "đã soát xong, không lệch gì", đúng điều ngược lại với sự thật. */
      (ten.length
        ? '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
            '<th style="text-align:left">Cơ sở</th><th>MoMo trên máy POS</th><th>MoMo theo sao kê</th>' +
            '<th>Phí</th><th>Doanh thu MoMo đã trừ phí</th>' +
            '<th>Lệch</th><th>Ngày so được</th><th>Ngày thiếu file</th>' +
          '</tr></thead><tbody>' + hang +
          '<tr style="font-weight:700;border-top:2px solid var(--line-2)">' +
            '<td style="text-align:left">Tất cả ' + ten.length + ' cơ sở <b>so được</b></td>' +
            '<td class="s">' + tien(TP) + '</td><td class="s">' + tien(TM) + '</td>' +
            '<td class="s">' + (TF ? tien(TF) : '—') + '</td>' +
            '<td class="s">' + (TF ? tien(TM - TF) : '—') + '</td>' +
            '<td class="s">' + nguyen(TP - TM) + '</td><td></td><td></td></tr>' +
          '</tbody></table></div>'
        : '<div class="trong" style="margin-top:10px">Chưa cơ sở nào so được — sổ MoMo đang nạp ' +
          'không phủ cơ sở nào có doanh thu MoMo trong kỳ.</div>') + khoiPhi + khoiChuaSo +
      '<div class="chu-them" style="margin-top:10px"><b>Cách đọc:</b> lệch <b>dương</b> là máy POS ' +
      'ghi nhiều hơn MoMo nhận — thường do bấm nhầm hình thức thanh toán, hoặc đơn huỷ mà máy vẫn ' +
      'ghi. Lệch <b>âm</b> nặng hơn: MoMo nhận tiền mà máy không ghi.<br>' +
      '<b>Lệch KHÔNG trừ phí</b> — cố ý. Phí là khoản MoMo thu, không phải chỗ hai bên ghi khác ' +
      'nhau; trừ phí vào Lệch thì cơ sở nào cũng "lệch" đúng bằng phí và cột ấy hết tác dụng. ' +
      'Phí nhập tay theo khoảng ngày × tài khoản (MoMo chỉ đưa một số tổng ở màn Đối soát), rồi ' +
      'chia về cơ sở theo <b>% doanh thu</b>; cộng các phần lại đúng bằng số đã nhập.</div></div>';
  }

  /**
   * BẢNG LỆCH TỪNG GIAO DỊCH MOMO.
   *
   * 🔴 SO TỔNG MỘT NGÀY THÌ HAI LỖI NGƯỢC CHIỀU TRIỆT TIÊU NHAU. Một giao dịch MoMo nhận mà máy
   *    không ghi, cộng một giao dịch máy ghi mà MoMo không nhận — tổng vẫn khớp, mà thực tế là
   *    HAI cái sai. Anh Thắng 16/09/2026: *"2 bên cũng hay lỗi do nhận tiền lỗi, nên cần có bảng
   *    lệch giao dịch giữa 2 bên"*. Xuống tới từng mã thì cả hai hiện ra.
   */
  function taiMomoGd(o, k) {
    var noi = o.querySelector('#dtMomoGdNoi');
    if (!noi) return;
    api('momo-gd?tu=' + k.tu + '&den=' + k.den).then(function (r) {
      if (!r.co_pos && !r.co_sk) {
        var khung = o.querySelector('#dtMomoGd');
        if (khung) khung.hidden = true;
        return;
      }
      if (r.sk_gop) {
        noi.innerHTML = '<div class="canh-ghep">Sổ MoMo đang khai (<code>' + esc(r.sk_bang) + '</code>) là ' +
          '<b>sổ đã gộp theo ngày</b> — mỗi dòng là cả một ngày của một quán, có cột đếm giao dịch. ' +
          'Tổng ngày × cơ sở thì nó đúng và bảng <b>Tổng hợp cả kỳ</b> đang dùng được ngay.<br>' +
          'Nhưng đối soát <b>từng giao dịch</b> thì phải có từng giao dịch. Sổ ấy dựng từ chính mấy ' +
          'file <code>Transaction_report_….csv</code> — nạp thẳng file ấy là có ngay bảng lệch tới ' +
          'từng mã, ghép bằng Mã giao dịch.' +
          '<div style="margin-top:8px">' + NUT_NAP_MOMO_SK + '</div></div>';
        return;
      }
      if (!r.co_pos || !r.co_sk) {
        noi.innerHTML = '<div class="trong">' + (!r.co_pos
          ? 'Chưa nạp file <b>Giao dịch MoMo (FABi)</b>.' +
            '<div style="margin-top:8px"><button class="nut" type="button" ' +
            'data-mo-nap="momo_pos">⬆️ Nạp giao dịch MoMo (FABi)</button></div>'
          : 'Chưa có <b>sổ MoMo</b> — cần file <code>Transaction_report_….csv</code> xuất từ trang ' +
            'quản lý MoMo (<b>Giao dịch → Xuất báo cáo</b>), hoặc khai bảng sẵn có ở Quản trị → ' +
            'Khai sổ MoMo.' +
            '<div style="margin-top:8px">' + NUT_NAP_MOMO_SK + '</div>') +
          '</div>';
        return;
      }
      /* Sổ MoMo chỉ phủ mấy quán dùng mã MoMo riêng — nói thẳng ra để không ai hiểu nhầm là
         những quán khác "không có giao dịch MoMo nào". */
      var pv = r.pham_vi || { co_so: [] };
      var h = '';
      if ((pv.co_so || []).length) {
        h += '<div class="chu-them" style="margin:2px 0 10px">Sổ MoMo đang phủ <b>' +
          pv.co_so.length + ' cơ sở</b> (' + esc(pv.co_so.join(' · ')) + ')' +
          (pv.ngay_dau ? ', từ ' + ngayVN(pv.ngay_dau) + ' đến ' + ngayVN(pv.ngay_cuoi) : '') +
          '. Chỉ những giao dịch nằm trong phạm vi ấy mới đem ra kết luận.</div>';
      }
      if (pv.pos_cuoi && pv.ngay_cuoi && pv.pos_cuoi < pv.ngay_cuoi) {
        h += '<div class="canh-ghep">⚠️ <b>Hai file cắt khác mốc.</b> Sổ MoMo có tới ' +
          ngayVN(pv.ngay_cuoi) + ' còn bản xuất FABi mới tới ' + ngayVN(pv.pos_cuoi) + '. ' +
          'Giao dịch MoMo của mấy ngày dôi ra em để riêng ở <b>Ngoài kỳ kho POS</b>, không kể là ' +
          'máy bỏ sót — nạp bản xuất FABi mới hơn là hết.</div>';
      }
      h += '<div class="the-hang" style="margin:6px 0 14px">' +
        the_nho('Khớp hai bên', nguyen(r.so_khop) + ' / ' + nguyen(r.so_pos), '') +
        the_nho('Máy ghi, MoMo không có', nguyen(r.so_chi_pos) + ' · ' + tien(r.tien_chi_pos),
          r.so_chi_pos ? 'xau' : '') +
        the_nho('MoMo có, máy không ghi', nguyen(r.so_chi_sk) + ' · ' + tien(r.tien_chi_sk),
          r.so_chi_sk ? 'xau' : '') +
        the_nho('Lệch số tiền', nguyen(r.so_lech), r.so_lech ? 'xau' : '') +
        (r.so_loi_pos ? the_nho('Máy ghi lỗi / huỷ', nguyen(r.so_loi_pos), '') : '') +
        (r.so_ngoai ? the_nho('Ngoài phạm vi sổ MoMo', nguyen(r.so_ngoai) + ' · ' + tien(r.tien_ngoai), '') : '') +
        (r.so_ngoai_sk ? the_nho('Ngoài kỳ kho POS', nguyen(r.so_ngoai_sk) + ' · ' + tien(r.tien_ngoai_sk), '') : '') +
        '</div>';

      /* `khoa` là ô nhớ số trang RIÊNG của từng bảng — ba bảng này nằm cùng một màn và không
         dài bằng nhau, dùng chung một ô nhớ thì bấm sang trang 3 ở bảng dài là hai bảng ngắn
         trống trơn. */
      function bang(tieu, cot, dong, ghi, khoa) {
        if (!dong.length) return '';
        var pt = catTrang(khoa || tieu, dong);
        return '<h3 class="tieu-nho">' + esc(tieu) + '</h3>' +
          (ghi ? '<div class="chu-them">' + ghi + '</div>' : '') +
          '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          cot.map(function (c) { return '<th style="text-align:left">' + esc(c) + '</th>'; }).join('') +
          '</tr></thead><tbody>' + pt.dong.join('') + '</tbody></table></div>' +
          thanhTrang(khoa || tieu, pt);
      }

      h += bang('Máy POS ghi mà sổ MoMo không có (' + nguyen(r.so_chi_pos) + ')',
        ['Ngày', 'Cơ sở', 'Mã đối tác', 'Mã hoá đơn', 'Số tiền'],
        (r.chi_pos || []).map(function (x) {
          return '<tr><td style="text-align:left">' + esc(ngayVN(x.ngay)) + ' ' + esc(String(x.gio)) + 'h</td>' +
            '<td style="text-align:left">' + esc(String(x.cua_hang).slice(0, 30)) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.ma_doi_tac) + '</code></td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.ma_hd) + '</code></td>' +
            '<td class="s">' + tien(x.so_tien) + '</td></tr>';
        }),
        'Máy tính tiền ghi là khách đã trả qua MoMo, nhưng sổ MoMo không có giao dịch ấy — ' +
        'thường là bấm nhầm hình thức thanh toán, hoặc giao dịch rớt giữa chừng mà máy vẫn chốt đơn.',
        'gd_chi_pos');

      h += bang('Sổ MoMo có mà máy POS không ghi (' + nguyen(r.so_chi_sk) + ')',
        ['Ngày', 'Tên bên MoMo', 'Mã giao dịch', 'Số tiền'],
        (r.chi_sk || []).map(function (x) {
          return '<tr><td style="text-align:left">' + esc(x.ngay) + '</td>' +
            '<td style="text-align:left">' + esc(String(x.ten).slice(0, 30)) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.ma) + '</code></td>' +
            '<td class="s">' + tien(x.tien) + '</td></tr>';
        }),
        '<b>Nhóm này nặng hơn:</b> MoMo đã nhận tiền của khách mà máy tính tiền không ghi đơn nào — ' +
        'tiền vào tài khoản nhưng không nằm trong doanh thu.',
        'gd_chi_sk');

      h += bang('Khớp mã nhưng lệch số tiền (' + nguyen(r.so_lech) + ')',
        ['Ngày', 'Cơ sở', 'Mã đối tác', 'Máy POS', 'Sổ MoMo', 'Lệch'],
        (r.lech || []).map(function (x) {
          var p = x.pos;
          return '<tr><td style="text-align:left">' + esc(ngayVN(p.ngay)) + '</td>' +
            '<td style="text-align:left">' + esc(String(p.cua_hang).slice(0, 26)) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(p.ma_doi_tac) + '</code></td>' +
            '<td class="s">' + tien(p.so_tien) + '</td><td class="s">' + tien(x.sk_tien) + '</td>' +
            '<td class="s"><b style="color:var(--xau)">' + nguyen(p.so_tien - x.sk_tien) + '</b></td></tr>';
        }), '');

      h += bang('Ngoài kỳ kho POS (' + nguyen(r.so_ngoai_sk) + ')',
        ['Ngày', 'Tên bên MoMo', 'Mã giao dịch', 'Số tiền'],
        (r.ngoai_sk || []).map(function (x) {
          return '<tr><td style="text-align:left">' + esc(x.ngay) + '</td>' +
            '<td style="text-align:left">' + esc(String(x.ten).slice(0, 30)) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.ma) + '</code></td>' +
            '<td class="s">' + tien(x.tien) + '</td></tr>';
        }),
        '<b>Đây KHÔNG phải lệch.</b> MoMo có giao dịch của những ngày mà kho POS chưa có số — ' +
        'bản xuất FABi cắt sớm hơn. Nạp bản xuất FABi mới hơn rồi xem lại.');

      h += bang('Ngoài phạm vi sổ MoMo (' + nguyen(r.so_ngoai) + ')',
        ['Ngày', 'Cơ sở', 'Mã đối tác', 'Số tiền'],
        (r.ngoai || []).map(function (x) {
          return '<tr><td style="text-align:left">' + esc(ngayVN(x.ngay)) + '</td>' +
            '<td style="text-align:left">' + esc(String(x.cua_hang).slice(0, 30)) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.ma_doi_tac) + '</code></td>' +
            '<td class="s">' + tien(x.so_tien) + '</td></tr>';
        }),
        '<b>Đây KHÔNG phải lệch.</b> Máy POS ghi MoMo cho những cơ sở (hoặc những ngày) chưa có ' +
        'trong sổ MoMo đã nạp — muốn đối soát nốt thì tải thêm sao kê MoMo của các cơ sở ấy.');

      if (!r.so_chi_pos && !r.so_chi_sk && !r.so_lech) {
        h += '<div class="trong">Hai bên khớp từng giao dịch — không có dòng nào lệch.</div>';
      }
      noi.innerHTML = h;
    }).catch(function (e) {
      noi.innerHTML = '<div class="trong">' + esc(e.message || e) + '</div>';
    });
  }

  function thangCua(ngay) { return String(ngay || ymd(new Date())).slice(0, 7); }

  function taiLich(o, thang) {
    S.lich.thang = thang;
    var noi = o.querySelector('#dsLichNoi');
    var oT = o.querySelector('#dsThang');
    if (oT && !oT.dataset.noi) {
      oT.dataset.noi = '1';
      oT.addEventListener('change', function () { if (oT.value) taiLich(o, oT.value); });
    }
    if (!noi) return;
    noi.innerHTML = '<div class="trong">Đang tải…</div>';
    var cuoi = new Date(parseInt(thang.slice(0, 4), 10), parseInt(thang.slice(5, 7), 10), 0);
    api('doi-soat?tu=' + thang + '-01&den=' + ymd(cuoi) +
        '&cua_hang=' + encodeURIComponent(S.ds.ch)).then(function (r) {
      noi.innerHTML = (r.dong || []).length
        ? veLich(noi, r, thang)
        : '<div class="trong">Tháng này chưa có số liệu POS nào trong kho.</div>';
    }).catch(function (e) {
      noi.innerHTML = '<div class="trong">' + esc(e.message || e) + '</div>';
    });
  }

  /* ================= LỊCH NỘP TIỀN — nhìn cả tháng trong một màn ================= */
  /*
   * Anh Thắng 16/09/2026: *"như cơ sở này nó theo ngày, nhưng có bảng nào nhìn 1 tháng biết bạn
   * nộp ngày nào trong tháng không"*.
   *
   * Bảng theo dòng trả lời được từng ngày, nhưng để thấy THÓI QUEN thì phải đọc 31 dòng rồi tự
   * nhớ trong đầu — mà thứ cần thấy lại chính là hình dạng: quán này nộp đều, quán kia cứ cuối
   * tuần là đứt, quán nọ dồn ba ngày nộp một lần. Một lưới cơ sở × ngày bày ngay ra chuyện đó.
   *
   * 🔴 MÀU ĐI KÈM KÝ HIỆU VÀ CHỮ, KHÔNG BAO GIỜ CHỈ CÓ MÀU. Khoảng 1 trong 12 đàn ông không
   *    phân biệt được đỏ với xanh lá — mà đây đúng là cặp màu mang nghĩa "mất tiền" với "xong".
   *    Nên mỗi ô có một dấu (✓ ▲ ✕ ·), và di chuột vào là hiện đủ số.
   */
  function veLich(o, r, thang) {
    var ds = r.dong || [];
    var n = new Date(parseInt(thang.slice(0, 4), 10), parseInt(thang.slice(5, 7), 10), 0).getDate();
    var ch = [], theo = {};
    ds.forEach(function (x) {
      if (!theo[x.cua_hang]) { theo[x.cua_hang] = {}; ch.push(x.cua_hang); }
      theo[x.cua_hang][parseInt(x.ngay.slice(8, 10), 10)] = x;
    });
    ch.sort();

    var h = '<div class="lich-cuon"><table class="lich"><thead><tr><th class="ten">Cơ sở</th>';
    for (var d = 1; d <= n; d++) {
      var wd = new Date(thang + '-' + ('0' + d).slice(-2) + 'T00:00:00').getDay();
      h += '<th' + (0 === wd ? ' class="cn"' : '') + '>' + d + '</th>';
    }
    h += '<th class="ten">Còn treo</th></tr></thead><tbody>';

    var nhac = r.ngay_nhac || 10;
    ch.forEach(function (c) {
      var xong = 0, coNgay = 0, treoCuoi = 0, ngayCuoi = '';
      var o_ = '<tr><td class="ten" title="' + esc(c) + '">' + esc(String(c).slice(0, 30)) + '</td>';
      for (var d = 1; d <= n; d++) {
        var x = theo[c][d];
        if (!x) { o_ += '<td class="lo lo-rong"></td>'; continue; }
        coNgay++;
        if (x.ngay > ngayCuoi) { ngayCuoi = x.ngay; treoCuoi = x.treo || 0; }
        var t = trangThaiNop(x, nhac);
        if (t.ma === 'du') xong++;
        o_ += '<td class="lo lo-' + t.ma + '"" title="' + esc(ngayVN(x.ngay) + ' · ' + c + '\n' +
          'Tiền mặt POS: ' + tien(x.pos_tm) + '\n' +
          'Ngân hàng nhận: ' + (x.co_bank ? tien(x.nop_bank) : 'chưa có') + '\n' + t.chu) + '">' +
          t.dau + '</td>';
      }
      /* Cột cuối là SỐ DƯ TREO CUỐI KỲ, không phải tổng mấy ngày thiếu cộng lại — cộng lại là
         đếm cùng một khoản tiền nhiều lần. */
      o_ += '<td class="ten s">' + (treoCuoi > 1000 ? '<b style="color:var(--xau)">' + nguyen(treoCuoi) + '</b>'
        : (coNgay ? '<span style="color:var(--tot)">sạch</span>' : '—')) + '</td></tr>';
      h += o_;
    });
    h += '</tbody></table></div>' + thanhTrang('doi_soat', ptDS);

    /* Chú giải luôn có mặt — ký hiệu + chữ, để không ai phải đoán màu nghĩa là gì. */
    h += '<div class="lich-chu">' +
      '<span><i class="lo lo-du">✓</i> đã nộp đủ (gồm ngày được lần nộp sau xoá sạch)</span>' +
      '<span><i class="lo lo-cho">·</i> đang dồn, chưa tới hạn</span>' +
      '<span><i class="lo lo-chua">✕</i> treo quá lâu</span>' +
      '<span><i class="lo lo-cho">?</i> chưa khai mã nộp tiền</span>' +
      '<span><i class="lo lo-khong"></i> không có tiền mặt</span>' +
      '<span><i class="lo lo-rong"></i> không có số liệu POS</span>' +
      '</div>';
    return h;
  }

  /** Một ngày × cơ sở ở trạng thái nào — dùng chung cho lịch và cho thẻ trong bảng. */
  /**
   * Một ô trong lịch.
   *
   * 🔴 NGÀY CÓ TIỀN VỀ MỚI LÀ ✓; ngày không có giao dịch chỉ là ngày TIỀN DỒN LÊN, không phải
   *    ngày sai phạm. Cửa hàng trưởng nộp gộp mỗi tuần một lần là lối làm bình thường ở nhà mình.
   *    Ô đỏ chỉ dành cho lúc tiền đã treo quá lâu và quá nhiều.
   */
  function trangThaiNop(x, nhac) {
    nhac = nhac || 10;
    if (!x.co_ma && !x.co_bank) return { ma: 'cho', dau: '?', chu: 'chưa khai mã nộp tiền cho cơ sở này' };
    if (x.nop_bank > 0) {
      return { ma: 'du', dau: '✓', chu: 'ngân hàng nhận ' + tien(x.nop_bank) +
        (x.treo > 1000 ? ' — còn treo ' + tien(x.treo) : ' — sạch') };
    }
    if (!x.phai_nop || x.phai_nop <= 0) return { ma: 'khong', dau: '', chu: 'không có tiền mặt' };
    var t = x.treo || 0;
    if (t < 1000) return { ma: 'du', dau: '✓', chu: 'không còn treo đồng nào' };
    /* Ngày dồn tiền đã được cú nộp sau đó xoá sạch — tích luôn, xem khối chú thích ở máy chủ. */
    if (x.da_xong) {
      return { ma: 'du', dau: '✓', chu: 'tiền của ngày này đã nằm trong lần nộp ' +
        ngayVN(x.nop_gan_nhat || '') };
    }
    if ((x.ngay_treo || 0) >= nhac) {
      return { ma: 'chua', dau: '✕', chu: 'treo ' + tien(t) + ' — ' + x.ngay_treo + ' ngày chưa nộp' };
    }
    return { ma: 'cho', dau: '·', chu: 'đang dồn ' + tien(t) +
      (x.ngay_treo ? ' — ' + x.ngay_treo + ' ngày kể từ lần nộp gần nhất' : '') };
  }

  /**
   * Ô trả lời câu "tiền mặt của quán này đang nằm ở đâu".
   *
   * 🔴 ĐO THEO SỐ DƯ TREO VÀ SỐ NGÀY, KHÔNG PHẢI THEO TỪNG NGÀY CÓ NỘP HAY KHÔNG. Cửa hàng
   *    trưởng gom mấy ngày nộp một cục (sao kê TÀU GÒ VẤP: 4 lần trong 2 tháng), nên "hôm nay
   *    không thấy giao dịch" là chuyện bình thường, không phải dấu hiệu gì.
   *
   * 🔴 SỐ ĐẦY ĐỦ, KHÔNG LÀM TRÒN. Anh Thắng 16/09/2026: *"chỗ 40tr cần là con số chính xác để
   *    đối chiếu với ngân hàng"*. "về 40 tr" thì đẹp mắt nhưng không tra được — người ta phải mở
   *    sao kê tìm dòng 39.870.000.
   */
  function theTreo(x, coBank, ng, nhac) {
    if (!coBank) return '<span class="chip cho">chưa nạp sao kê</span>';
    if (!x.co_ma && !x.co_bank) return '<span class="chip cho">chưa khai mã nộp tiền</span>';
    var t = x.treo || 0;
    var h = x.nop_bank > 0
      ? '<span class="chip du" title="Số ngân hàng nhận được theo sao kê' +
        (x.nop_lan > 1 ? ' — ' + x.nop_lan + ' lần chuyển' : '') + '">về ' + nguyen(x.nop_bank) + '</span>'
      : '';
    if (t < 1000) return h || '<span class="chip du">✓ đã nộp đủ</span>';
    /* Ngày dồn tiền nhưng ĐÃ được một cú nộp sau đó xoá sạch — tích lại cho khỏi tưởng còn nợ. */
    if (x.da_xong) {
      return h + '<span class="chip du" title="Tiền của ngày này đã nằm trong lần nộp ' +
        esc(ngayVN(x.nop_gan_nhat || '')) + '">✓ đã nộp đủ</span>';
    }
    var lau = (x.ngay_treo || 0) >= (nhac || 10) && t > ng.so_tien;
    return h + '<span class="chip ' + (lau ? 'thieu' : '') + '" title="Cộng dồn tiền mặt phải nộp trừ tiền đã về">' +
      'treo ' + nguyen(t) + (x.ngay_treo ? ' · ' + x.ngay_treo + ' ngày' : '') + '</span>';
  }

  function canhBao(x, ng, nhac) {
    /* Treo lâu mới là dấu hiệu, chứ không phải một ngày không có giao dịch. */
    var m = ((x.ngay_treo || 0) >= (nhac || 10) && (x.treo || 0) > ng.so_tien) ? x.treo : 0;
    if (x.co_bao_cao) {
      m = Math.max(m, Math.abs(x.lech_tm || 0), Math.abs(x.chua_nop || 0), Math.abs(x.lech_nop || 0));
    }
    if (!m) return false;
    return m > ng.so_tien || (x.doanh_thu > 0 && m / x.doanh_thu * 100 > ng.phan_tram);
  }

  function the_nho(nhan, giatri, lop) {
    return '<section class="the"><div class="lb">' + esc(nhan) + '</div>' +
      '<div class="v"' + (lop === 'xau' ? ' style="color:var(--xau)"' : '') + '>' + esc(giatri) + '</div></section>';
  }

  /* ================= tab QUẢN TRỊ ================= */
  function taiQuanTri() {
    var o = q('#dtTabQuanTri');
    o.innerHTML = '<div class="khung"><div class="trong">Đang tải…</div></div>';
    Promise.all([api('nhan-su'), api('ghep-co-so'), api('sao-ke')]).then(function (kq) {
      veQuanTri(o, kq[0]);
      veGhep(o, kq[1]);
      veSaoKe(o, kq[2]);
      /* Hộp thư xin riêng, và KHÔNG được làm hỏng cả tab nếu hỏng: cấu hình hộp thư chỉ quản
         trị viên mới đọc được, nên người dùng thường sẽ nhận 403 ở đây. Gộp vào `Promise.all`
         ở trên là một cái 403 làm trắng cả tab Quản trị của họ. */
      api('hop-thu').then(function (r) { veHopThu(o, r); }).catch(function () {});
      /* Bóc tách vé → khách: cũng xin riêng, chỉ người được nạp file (văn phòng) mới đọc được. */
      taiVeKhach(o);
      /* Nhóm món nào là sale vé, nhóm nào bán lẻ — cũng xin riêng, cùng cửa quyền, cùng cửa hàng đang chọn. */
      taiNhomVe(o);
      /* Quy trình báo cáo cơ sở hằng ngày: cấu hình + tổng hợp hôm qua + nhật ký (chỉ quản trị). */
      taiQuyTrinh(o);
    }).catch(function (e) {
      o.innerHTML = '<div class="khung"><div class="trong">' + esc(e.message || e) + '</div></div>';
    });
  }

  /* ---- QUY TRÌNH BÁO CÁO CƠ SỞ HẰNG NGÀY ----
     Anh Thắng 24/09/2026: *"Làm quy trình báo cáo hằng ngày tự động"* — *"Báo cáo cơ sở thôi"*.
     Máy chủ theo dõi bốn bước từng ngày × cơ sở, đến giờ hạn thì tổng hợp + gửi thư cho văn phòng. */
  function taiQuyTrinh(o) {
    api('quy-trinh').then(function (r) { if (r && r.cf) veQuyTrinh(o, r); })
      .catch(function () {});
  }

  function veQuyTrinh(o, r) {
    var c = r.cf || {}, th = r.tong_hop || { dem: {}, dong: [], tong: 0 }, d = th.dem || {};
    var chuaXong = (d.chua_nop || 0) + (d.chua_fabi || 0) + (d.da_luu || 0);
    var h = '<div class="khung" id="dtQuyTrinh"><header><h2>Quy trình báo cáo cơ sở hằng ngày</h2>' +
      '<span class="goi">' + (c.bat ? 'đang bật · hạn chốt ' + esc(c.han || '10:00') + ' sáng hôm sau' : 'đang tắt') + '</span></header>' +
      '<div class="chu-them">Mỗi ngày bán hàng đi qua bốn bước: <b>số máy POS về</b> (hộp thư 08:02) → <b>cơ sở khai</b> ' +
      '(soát hàng bán, đếm két, khách vào) → <b>sổ kho</b> → <b>Lưu và chốt</b>. Cửa hàng trưởng mở tab Nhập báo cáo ' +
      'thấy ngay ngày nào còn treo; đến giờ hạn hệ tổng hợp mọi cơ sở, ghi nhật ký và gửi <b>một thư</b> cho văn phòng. ' +
      '<b>Hệ không tự điền số</b> thay cơ sở — két, khách là số người đếm.</div>';

    var o1 = function (nhan, ten, gt, kieu, rong) {
      return '<label class="o" style="margin:0 8px 8px 0">' + esc(nhan) +
        '<input type="' + (kieu || 'text') + '" data-qt="' + ten + '" value="' + esc(gt == null ? '' : gt) + '" style="width:' + (rong || 160) + 'px"></label>';
    };
    h += '<div style="margin-top:12px">' +
      '<label class="o" style="margin:0 8px 8px 0"><input type="checkbox" data-qt="bat"' + (c.bat ? ' checked' : '') + '> Bật theo dõi và tổng hợp hằng ngày</label>' +
      o1('Hạn chốt (giờ sáng hôm sau)', 'han', c.han || '10:00', 'time', 110) +
      o1('Nhìn lùi (ngày)', 'lui', c.lui || 7, 'number', 70) +
      '</div><div>' +
      o1('Thư tổng hợp gửi tới (nhiều địa chỉ, cách nhau dấu phẩy)', 'email', c.email, 'text', 340) +
      '</div>' +
      '<div class="chu-them">Trống ô thư thì hệ chỉ ghi nhật ký. Thư gửi bằng bộ gửi thư của WordPress — hosting phải cho gửi thư.</div>' +
      '<div style="margin-top:10px">' +
      '<button class="nut chinh" type="button" id="dtQtLuu">Lưu</button> ' +
      '<button class="nut" type="button" id="dtQtChay">Tổng hợp và gửi ngay</button>' +
      (r.lan_sau ? '<span class="chu-them" style="margin-left:10px">Lượt sau: ' + esc(new Date(r.lan_sau).toLocaleString('vi-VN')) + '</span>' : '') +
      '</div>';
    if (r.vua_chay) {
      h += '<div class="canh-ghep" style="margin-top:10px">' + (r.vua_chay.gui
        ? '✓ Đã gửi <b>' + esc(r.vua_chay.tieu_de) + '</b> tới ' + esc((r.vua_chay.toi || []).join(', '))
        : (r.vua_chay.loi ? '⚠️ ' + esc(r.vua_chay.loi) : 'Đã tổng hợp, ghi nhật ký. Chưa gửi thư vì chưa có địa chỉ.')) + '</div>';
    }

    /* Hôm qua: từng cơ sở một dòng. */
    h += '<h3 class="tieu-nho" style="margin-top:14px">Hôm qua ' + esc(ngayVN(r.hom_qua || '')) + ': <b>' + (d.da_chot || 0) + '/' + (th.tong || 0) + ' đã chốt</b>' +
      (chuaXong ? ' · <b style="color:var(--xau)">' + chuaXong + ' chưa xong</b>' : '') + '</h3>';
    if (!(th.dong || []).length) {
      h += '<div class="trong">Chưa có cơ sở nào trong kho POS.</div>';
    } else {
      h += '<div class="bang-the bang-cuon"><table><thead><tr><th style="text-align:left">Cơ sở</th><th>Máy POS</th><th>Trạng thái</th><th>Két lệch</th><th>Món lệch</th><th>Người</th></tr></thead><tbody>' +
        th.dong.map(function (x) {
          return '<tr' + (x.qua_han ? ' class="qua-han"' : '') + '><td data-nhan="Cơ sở" style="text-align:left">' + esc(x.cua_hang) + '</td>' +
            '<td class="s" data-nhan="Máy POS">' + (x.doanh_thu != null ? tien(x.doanh_thu) : '—') + '</td>' +
            '<td data-nhan="Trạng thái"><b' + ('da_chot' === x.trang_thai ? ' style="color:var(--tot)"' : (x.qua_han ? ' style="color:var(--xau)"' : '')) + '>' + esc(QT_NHAN[x.trang_thai] || x.trang_thai) + (x.qua_han ? ' · quá hạn' : '') + '</b></td>' +
            '<td class="s" data-nhan="Két lệch">' + (x.lech_ket != null ? (x.lech_ket > 0 ? '+' : '') + tien(x.lech_ket) : '—') + '</td>' +
            '<td class="s" data-nhan="Món lệch">' + (x.mon_lech ? '<b style="color:var(--xau)">' + nguyen(x.mon_lech) + '</b>' : (x.buoc && x.buoc.khai ? 'khớp' : '—')) + '</td>' +
            '<td data-nhan="Người">' + esc(x.nguoi || '') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }

    var nk = r.nhat_ky || [];
    h += '<h3 class="tieu-nho" style="margin-top:14px">Nhật ký 30 lượt gần nhất</h3>';
    if (!nk.length) {
      h += '<div class="chu-them">Chưa chạy lượt nào — đến giờ hạn hệ tự chạy, hoặc bấm "Tổng hợp và gửi ngay".</div>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr><th style="text-align:left">Lúc</th><th>Ngày</th><th>Chốt</th><th>Chưa xong</th><th style="text-align:left">Thư</th></tr></thead><tbody>' +
        nk.slice(0, 30).map(function (x) {
          var dd = x.dem || {};
          return '<tr><td style="text-align:left" class="s">' + esc(x.luc) + '</td><td>' + esc(ngayVN(x.ngay)) + '</td>' +
            '<td>' + nguyen(dd.da_chot || 0) + '/' + nguyen(x.tong || 0) + '</td>' +
            '<td>' + nguyen((dd.da_luu || 0) + (dd.chua_nop || 0) + (dd.chua_fabi || 0)) + '</td>' +
            '<td style="text-align:left">' + (x.gui ? 'đã gửi ' + esc((x.toi || []).join(', ')) : (x.loi ? '<span style="color:var(--xau)">' + esc(x.loi) + '</span>' : '<span class="chu-them">không gửi (chưa có địa chỉ)</span>')) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }
    h += '</div>';
    var cu = o.querySelector('#dtQuyTrinh');
    if (cu) { cu.outerHTML = h; } else { o.insertAdjacentHTML('beforeend', h); }
    noiQuyTrinh(o);
  }

  function noiQuyTrinh(o) {
    var k = o.querySelector('#dtQuyTrinh');
    if (!k) return;
    var thu = function () {
      var fd = new FormData();
      Array.prototype.forEach.call(k.querySelectorAll('[data-qt]'), function (x) {
        var n = x.getAttribute('data-qt');
        if ('checkbox' === x.type) { fd.append(n, x.checked ? '1' : ''); return; }
        fd.append(n, x.value);
      });
      return fd;
    };
    var luu = k.querySelector('#dtQtLuu'), chay = k.querySelector('#dtQtChay');
    luu.addEventListener('click', function () {
      luu.disabled = true; luu.textContent = 'Đang lưu…';
      api('quy-trinh', { method: 'POST', body: thu() }).then(function (r) { veQuyTrinh(o, r); })
        .catch(function (e) { luu.disabled = false; luu.textContent = 'Lưu'; window.alert(e.message || e); });
    });
    chay.addEventListener('click', function () {
      chay.disabled = true; chay.textContent = 'Đang chạy…';
      /* Lưu trước rồi chạy — bấm chạy sau khi vừa sửa ô thư mà chưa Lưu thì phải gửi tới địa chỉ mới. */
      api('quy-trinh', { method: 'POST', body: thu() })
        .then(function () { return api('quy-trinh-chay', { method: 'POST' }); })
        .then(function (r) { veQuyTrinh(o, r); })
        .catch(function (e) { chay.disabled = false; chay.textContent = 'Tổng hợp và gửi ngay'; window.alert(e.message || e); });
    });
  }

  /* ---- NHẬN BÁO CÁO QUA HỘP THƯ ----
     Anh Thắng 19/09/2026: *"đẩy dữ liệu fabi về mail hosting, xong web sẽ đọc mail lấy file đó,
     định kì 2 tiếng lần (có thể chỉnh được)"*. */
  var THU_LOAI = [
    ['pos', 'Báo cáo bán hàng FABi'],
    ['sao_ke', 'Sao kê ngân hàng'],
    ['momo_pos', 'MoMo trên máy POS'],
    ['momo_sk', 'Sao kê MoMo'],
    ['bes', 'Báo cáo Bes']
  ];

  function veHopThu(o, r) {
    var c = (r && r.cf) || {};
    var h = '<div class="khung" id="dtHopThu"><header><h2>Nhận báo cáo qua hộp thư</h2>' +
      '<span class="goi">' + (c.bat
        ? 'đang bật · ' + ('gio' === c.che_do ? 'mỗi ' + (c.gio || 2) + ' giờ' : 'hằng ngày lúc ' + (c.luc || '08:02'))
        : 'đang tắt') + '</span></header>' +
      '<div class="chu-them">FABi gửi báo cáo về một hộp thư riêng, hệ tự vào lấy tệp đính kèm ' +
      'theo giờ rồi nạp vào kho — <b>y như anh bấm nạp tay</b>, cùng một bộ đọc.</div>';

    /* 🔴 WP-Cron chỉ chạy khi CÓ NGƯỜI MỞ TRANG. Không nói ra chỗ này thì hệ đứng im cả đêm mà
       màn hình vẫn trông bình thường, tới lúc phát hiện là mất mấy ngày số liệu. */
    h += '<div class="canh-ghep" style="margin-top:10px">🔴 <b>Phải bật Cron Jobs bên hosting.</b> ' +
      'Lịch của WordPress chỉ chạy khi có người mở trang web — ban đêm không ai vào là cả đêm ' +
      'không lấy thư, mà màn hình vẫn trông bình thường. Vào hPanel → <b>Cron Jobs</b>, cho gọi ' +
      '<code>' + esc((r && r.cron_url) || 'wp-cron.php') + '</code> mỗi 5–10 phút. Nhịp mấy tiếng một ' +
      'lượt thì hệ tự giữ, cron kia chỉ để đánh thức.</div>';

    if (r && r.qua_han) {
      h += '<div class="canh-ghep" style="margin-top:8px;border-color:var(--xau)">⚠️ <b>Quá hạn mà ' +
        'chưa chạy.</b> Lượt lấy thư gần nhất đã lâu hơn hai nhịp — nhiều khả năng Cron Jobs bên ' +
        'hosting chưa bật, hoặc đang sai đường dẫn.</div>';
    }

    var o1 = function (nhan, ten, gt, kieu, rong) {
      return '<label class="o" style="margin:0 8px 8px 0">' + esc(nhan) +
        '<input type="' + (kieu || 'text') + '" data-thu="' + ten + '" value="' + esc(gt == null ? '' : gt) +
        '" style="width:' + (rong || 160) + 'px"></label>';
    };

    h += '<div style="margin-top:12px">' +
      '<label class="o" style="margin:0 8px 8px 0"><input type="checkbox" data-thu="bat"' +
        (c.bat ? ' checked' : '') + '> Bật tự lấy</label>' +
      /* FABi gửi đúng 08:00 mỗi sáng -> mặc định "hằng ngày lúc 08:02", một lượt. Kéo mỗi 2
         giờ là 11 lượt nối IMAP vô ích một ngày. Chế độ theo giờ giữ lại cho nguồn gửi bất chợt. */
      '<label class="o" style="margin:0 8px 8px 0">Chạy<select data-thu="che_do">' +
        '<option value="ngay"' + ('gio' !== c.che_do ? ' selected' : '') + '>hằng ngày, một lượt lúc…</option>' +
        '<option value="gio"' + ('gio' === c.che_do ? ' selected' : '') + '>mỗi N giờ</option>' +
      '</select></label>' +
      o1('Giờ lấy (HH:MM)', 'luc', c.luc || '08:02', 'time', 110) +
      o1('Nhịp (giờ)', 'gio', c.gio, 'number', 70) +
      '<label class="o" style="margin:0 8px 8px 0">Loại báo cáo<select data-thu="loai">' +
        THU_LOAI.map(function (x) {
          return '<option value="' + x[0] + '"' + (c.loai === x[0] ? ' selected' : '') + '>' + esc(x[1]) + '</option>';
        }).join('') + '</select></label>' +
      '</div><div>' +
      o1('Máy chủ thư', 'may', c.may, 'text', 200) +
      o1('Cổng', 'cong', c.cong, 'number', 80) +
      '<label class="o" style="margin:0 8px 8px 0">Bảo mật<select data-thu="bao_mat">' +
        ['ssl', 'starttls', 'khong'].map(function (x) {
          return '<option value="' + x + '"' + (c.bao_mat === x ? ' selected' : '') + '>' + x.toUpperCase() + '</option>';
        }).join('') + '</select></label>' +
      o1('Thư mục', 'thu_muc', c.thu_muc, 'text', 110) +
      '</div><div>' +
      o1('Địa chỉ hộp thư', 'nguoi', c.nguoi, 'text', 250) +
      '<label class="o" style="margin:0 8px 8px 0">Mật khẩu' +
        '<input type="password" data-thu="mat_khau" placeholder="' +
        (c.co_mat_khau ? '••••••• (đã đặt — bỏ trống là giữ nguyên)' : 'chưa đặt') +
        '" style="width:230px"' + (c.khoa_o_config ? ' disabled' : '') + '></label>' +
      '</div>';

    /* Mật khẩu nằm trong wp-config.php thì hơn — nói ra, đừng để người ta không biết là có lối ấy. */
    h += '<div class="chu-them">' + (c.khoa_o_config
      ? '✓ Mật khẩu đang lấy từ <code>KHH_DT_MAIL_PASS</code> trong <code>wp-config.php</code> — ' +
        'không nằm trong cơ sở dữ liệu. Ô trên khoá lại là đúng.'
      : 'Nên đặt mật khẩu bằng dòng <code>define( \'KHH_DT_MAIL_PASS\', \'…\' );</code> trong ' +
        '<code>wp-config.php</code>: để ở đó thì nó không nằm trong cơ sở dữ liệu, nên một bản ' +
        'sao lưu lọt ra ngoài cũng không kèm mật khẩu hộp thư.') + '</div>';

    h += '<div style="margin-top:10px">' +
      o1('Chỉ nhận thư từ', 'nguoi_gui', c.nguoi_gui, 'text', 280) +
      o1('Tên tệp khớp', 'mau_ten', c.mau_ten, 'text', 200) +
      o1('Tên miền link được tải', 'link_mien', c.link_mien, 'text', 220) +
      '</div>' +
      '<div class="chu-them">Thư <b>không đính kèm tệp</b> (FABi gửi kiểu này) thì hệ tìm <b>link tải</b> ' +
      'trong thân thư. Chỉ tải link <b>https</b> ở tên miền của người gửi hoặc tên miền gõ ở ô trên ' +
      '(cách nhau dấu phẩy, ví dụ <code>ipos.vn, s3.amazonaws.com</code>). Link trỏ tên miền lạ thì ' +
      'nhật ký nêu tên miền ấy để anh thêm. Link <b>đòi đăng nhập</b> trả về trang web thay vì tệp — ' +
      'hệ nhận ra và nói thẳng.</div>' +
      '<div class="chu-them">🔴 <b>Bỏ trống ô "Chỉ nhận thư từ" là hệ chối hết</b> — cố ý. Hộp thư ' +
      'nào cũng nhận được thư rác, mà một tệp .csv của người lạ đi thẳng vào kho doanh thu thì ' +
      'không ai nhìn ra ngay. Gõ đúng địa chỉ FABi gửi, hoặc cả tên miền kiểu <code>@fabi.vn</code>. ' +
      'Nhiều địa chỉ thì cách nhau dấu phẩy.</div>';

    if (r && r.canh_bao_nguoi_gui) {
      h += '<div class="canh-ghep" style="border-color:var(--xau)">🔴 ' + esc(r.canh_bao_nguoi_gui) + '</div>';
    }
    if ('bes' === c.loai) {
      h += '<div>' + o1('Tên cơ sở (Bes)', 'co_so', c.co_so, 'text', 250) + '</div>';
    }
    if ('momo_sk' === c.loai) {
      h += '<div>' + o1('Tài khoản MoMo', 'tai_khoan', c.tai_khoan, 'text', 120) + '</div>';
    }

    h += '<div style="margin-top:10px">' +
      '<button class="nut chinh" type="button" id="dtThuLuu">Lưu</button> ' +
      '<button class="nut" type="button" id="dtThuChay">Lấy thư ngay</button>' +
      (r && r.lan_sau ? '<span class="chu-them" style="margin-left:10px">Lượt sau: ' +
        esc(new Date(r.lan_sau).toLocaleString('vi-VN')) + '</span>' : '') +
      '</div>';

    h += veThuNhatKy((r && r.nhat_ky) || []);
    h += '</div>';

    var cu = o.querySelector('#dtHopThu');
    if (cu) { cu.outerHTML = h; } else { o.insertAdjacentHTML('beforeend', h); }
    noiHopThu(o);
  }

  function veThuNhatKy(nk) {
    if (!nk.length) {
      return '<div class="chu-them" style="margin-top:12px">Chưa chạy lượt nào.</div>';
    }
    /* Lượt mới nhất có địa chỉ gửi bị chối -> mỗi địa chỉ một nút "Thêm". Bấm là ghép vào ô
       "Chỉ nhận thư từ" rồi Lưu luôn — hết phải cuộn tìm, chép, gõ lại. Phép gác không nới:
       vẫn là người quyết định địa chỉ nào được vào. */
    var la = (nk[0] && nk[0].nguoi_gui_la) || [];
    var nutThem = !la.length ? '' :
      '<div class="canh-ghep" style="margin-top:12px">Lượt vừa rồi chối thư từ <b>' + la.length +
      ' địa chỉ</b> chưa nằm trong danh sách. Nếu đúng là FABi gửi, bấm để thêm và lưu:<br>' +
      la.map(function (a) {
        return '<button class="vien" type="button" data-thu-them="' + esc(a) + '" style="margin:6px 6px 0 0">＋ Thêm ' + esc(a) + '</button>';
      }).join('') + '</div>';
    return nutThem + '<h3 class="tieu-nho" style="margin-top:14px">Nhật ký 50 lượt gần nhất</h3>' +
      '<div class="bang-cuon"><table><thead><tr><th style="text-align:left">Lúc</th>' +
      '<th>Thư xem</th><th>Nạp được</th><th style="text-align:left">Chi tiết</th>' +
      '</tr></thead><tbody>' +
      nk.slice(0, 50).map(function (x) {
        var ct = x.loi
          ? '<span style="color:var(--xau)">' + esc(x.loi) + '</span>'
          : (x.nap || []).map(function (n) {
              return esc(n.ten) + ' (' + nguyen(n.da_ghi || 0) + ' dòng)';
            }).concat((x.bo || []).map(function (b) {
              return '<span class="chu-them">bỏ qua: ' + esc(b.ten || b.tu || '') + ' — ' + esc(b.vi) +
                (b.link ? ' <span style="word-break:break-all">[' + esc(b.link) + ']</span>' : '') + '</span>';
            })).join(' · ') || '<span class="chu-them">không có gì mới</span>';
        return '<tr><td style="text-align:left" class="s">' + esc(x.luc) + '</td>' +
          '<td>' + nguyen(x.xem || 0) + '</td><td>' + nguyen(x.so_nap || 0) + '</td>' +
          '<td style="text-align:left">' + ct + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function noiHopThu(o) {
    var k = o.querySelector('#dtHopThu');
    if (!k) return;
    var thu = function () {
      var fd = new FormData();
      Array.prototype.forEach.call(k.querySelectorAll('[data-thu]'), function (x) {
        var n = x.getAttribute('data-thu');
        if ('checkbox' === x.type) { fd.append(n, x.checked ? '1' : ''); return; }
        /* 🔴 Ô mật khẩu trống nghĩa là GIỮ NGUYÊN, không phải xoá — màn hình không bao giờ nhận
           được mật khẩu cũ nên nó không có gì để gửi lại. Gửi chuỗi rỗng lên là xoá mất. */
        if ('mat_khau' === n && '' === x.value) return;
        fd.append(n, x.value);
      });
      return fd;
    };
    /* Nút "＋ Thêm <địa chỉ>": ghép vào ô danh sách (không trùng) rồi bấm Lưu thay người ta. */
    k.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-thu-them]') : null;
      if (!b) return;
      e.preventDefault();
      var o = k.querySelector('[data-thu="nguoi_gui"]');
      if (!o) return;
      var dc = b.getAttribute('data-thu-them');
      var ds = o.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
      if (ds.indexOf(dc) < 0) ds.push(dc);
      o.value = ds.join(', ');
      var l = k.querySelector('#dtThuLuu');
      if (l) l.click();
    });

    var luu = k.querySelector('#dtThuLuu');
    if (luu) {
      luu.addEventListener('click', function () {
        luu.disabled = true; luu.textContent = 'Đang lưu…';
        api('hop-thu', { method: 'POST', body: thu() }).then(function (r) {
          veHopThu(o, r);
        }).catch(function (e) {
          luu.disabled = false; luu.textContent = 'Lưu';
          window.alert(e.message || e);
        });
      });
    }
    var chay = k.querySelector('#dtThuChay');
    if (chay) {
      chay.addEventListener('click', function () {
        chay.disabled = true; chay.textContent = 'Đang lấy…';
        /* Lưu trước rồi mới chạy: bấm "Lấy thư ngay" sau khi vừa sửa ô mà chưa Lưu thì nó chạy
           bằng cấu hình CŨ, rồi báo lỗi của cấu hình cũ — không cách nào hiểu nổi. */
        api('hop-thu', { method: 'POST', body: thu() })
          .then(function () { return api('hop-thu-chay', { method: 'POST' }); })
          .then(function (r) {
            veHopThu(o, r.xem);
            var c = r.chay || {};
            /* "Xem 4 thư, nạp được 0 tệp" mà không nói VÌ SAO là bắt người ta cuộn xuống nhật ký
               rồi tự đoán. Gom lý do bỏ qua theo nhóm, kèm địa chỉ gửi bị chối. */
            var ly = {};
            (c.bo || []).forEach(function (b) { ly[b.vi] = (ly[b.vi] || 0) + 1; });
            var lyDo = Object.keys(ly).map(function (k) { return '· ' + k + ' (' + ly[k] + ' thư)'; }).join('\n');
            window.alert(c.loi ? c.loi
              : 'Xem ' + (c.xem || 0) + ' thư, nạp được ' + (c.so_nap || 0) + ' tệp.' +
                (lyDo ? '\n\nBỏ qua vì:\n' + lyDo : '') +
                ((c.nguoi_gui_la || []).length
                  ? '\n\nĐịa chỉ gửi bị chối: ' + c.nguoi_gui_la.join(', ') +
                    '\nNếu đúng là FABi, bấm nút "Thêm …" ngay dưới nhật ký.'
                  : ''));
          })
          .catch(function (e) {
            chay.disabled = false; chay.textContent = 'Lấy thư ngay';
            window.alert(e.message || e);
          });
      });
    }
  }

  /* ---- SAO KÊ NGÂN HÀNG: giờ cắt + bảng nhận mặt cơ sở ----
     Cột "Thực nộp" lấy ở đây chứ không lấy số cơ sở tự khai: ai giữ tiền mà cũng tự khai mình
     nộp bao nhiêu thì con số ấy không kiểm được gì. */
  function veSaoKe(o, r) {
    var ch = r.cua_hang || [], chua = r.chua_gan || [], theo = r.theo_ch || [];
    var h = '<div class="khung" id="dtSaoKe"><header><h2>Sao kê ngân hàng</h2>' +
      '<span class="goi">' + (r.so_dong
        ? nguyen(r.so_dong) + ' khoản · ' + tien(r.tong) + ' · ' + ngayVN(r.tu_ngay) + ' → ' + ngayVN(r.den_ngay)
        : 'chưa nạp sao kê') + '</span></header>';

    /* Sổ sao kê có thể đã nằm sẵn trong chính MySQL này (plugin Sao Kê Ngân Hàng, hoặc cổng SePay
       trong plugin Ghế) — bày ra để chọn, khỏi tải file hàng tháng. */
    var ng = r.nguon_ds || [], dangChon = (r.nguon && r.nguon.bang) || '';
    if (ng.length || r.co_cong_ghe) {
      h += '<div class="canh-ghep">' +
        '<div style="margin-bottom:8px">Site này đã có sẵn sổ giao dịch ngân hàng — chọn đúng sổ ' +
        'rồi bấm kéo, khỏi tải file. ' + (r.tu_keo ? 'Đang <b>tự kéo mỗi giờ</b>.' : '') + '</div>' +
        '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">' +
        (ng.length
          ? '<select id="dtNguon">' + ng.map(function (x) {
              return '<option value="' + esc(x.bang) + '"' + (x.bang === dangChon ? ' selected' : '') + '>' +
                esc(x.bang) + ' — ' + nguyen(x.so_dong) + ' dòng' +
                (x.tu_ngay ? ' · ' + ngayVN(x.tu_ngay) + ' → ' + ngayVN(x.den_ngay) : '') + '</option>';
            }).join('') + '</select>'
          : '<span class="chu-them">Máy chưa dò ra sổ nào — chọn tay bên dưới.</span>') +
        '<button class="nut chinh" type="button" id="dtKeoSk">Kéo giao dịch về</button>' +
        (r.tu_keo ? '' : '<label class="o"><input type="checkbox" id="dtTuKeo" checked> tự kéo mỗi giờ</label>') +
        '</div>' +
        /* Mẫu hai dòng của sổ đang chọn — nhìn nội dung là biết ngay có phải sao kê hay không. */
        '<div id="dtNguonMau" class="chu-them" style="margin-top:8px"></div>' +
        '<div style="margin-top:8px"><button class="vien" type="button" id="dtNguonTay">' +
        'Không thấy sổ của mình? Chọn tay →</button> ' +
        '<button class="vien" type="button" id="dtNguonMomo">Khai sổ MoMo →</button></div>' +
        '<div id="dtNguonTayNoi" hidden style="margin-top:10px"></div>' +
        '</div>';
    }

    if (!r.so_dong) {
      h += '<div class="trong">Chưa có giao dịch nào. ' + (r.co_cong_ghe
        ? 'Bấm <b>Kéo giao dịch về</b> ở trên.'
        : 'Bấm <b>Nạp báo cáo</b> ở trên, chọn thẻ <b>Sao kê ngân hàng</b> rồi thả file .xlsx/.csv ' +
          'tải từ ngân hàng xuống.') + '</div>';
    } else if (chua.length) {
      var maLa = r.ma_la || [];
      h += '<div class="canh-ghep">Còn <b>' + nguyen(chua.length) + ' khoản</b> chưa nhận ra cơ sở' +
        (maLa.length ? ', đọc được <b>' + maLa.length + ' mã nộp tiền</b> lạ. Gán mã cho cơ sở là cả ' +
          'nhóm khoản mang mã ấy về sổ cùng lúc.' : '. Không đọc được mã nộp tiền nào trong nội dung — ' +
          'khai theo mẩu chữ ở bảng dưới.') + '</div>';

      if (maLa.length) {
        var ptMa = catTrang('ma_la', maLa);
        h += '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
            '<th style="text-align:left">Mã đọc được</th><th>Số khoản</th><th>Tổng tiền</th>' +
            '<th style="text-align:left">Cơ sở nào?</th></tr></thead><tbody>' +
          ptMa.dong.map(function (m) {
            return '<tr><td style="text-align:left"><code class="nd">' + esc(m.ma) + '</code>' +
              '<span class="nho" style="display:block">' + esc(String(m.vi_du).slice(0, 70)) + '</span></td>' +
              '<td class="s">' + nguyen(m.so_lan) + '</td><td class="s">' + tien(m.so_tien) + '</td>' +
              '<td style="text-align:left"><select data-ma-la="' + esc(m.ma) + '">' +
                '<option value="">— chọn cơ sở để gán —</option>' +
                ch.map(function (t) { return '<option value="' + esc(t) + '">' + esc(t) + '</option>'; }).join('') +
              '</select></td></tr>';
          }).join('') + '</tbody></table></div>' + thanhTrang('ma_la', ptMa);
      }

      h += '<details style="margin-top:10px"><summary>Xem từng khoản chưa gán (' + nguyen(chua.length) + ')</summary>' +
        '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
          '<th>Ngày</th><th>Số tiền</th><th style="text-align:left">Nội dung chuyển khoản</th><th>Tài khoản</th>' +
        '</tr></thead><tbody>' +
        chua.slice(0, 60).map(function (x) {
          return '<tr><td>' + esc(ngayVN(x.ngay)) + '</td><td class="s">' + tien(x.so_tien) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.noi_dung || '—') + '</code></td>' +
            '<td>' + esc(x.tai_khoan || '—') + '</td></tr>';
        }).join('') + '</tbody></table></div></details>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr><th style="text-align:left">Cơ sở</th>' +
        '<th>Số khoản</th><th>Tổng nhận được</th></tr></thead><tbody>' +
        theo.map(function (x) {
          return '<tr><td style="text-align:left">' + esc(x.cua_hang) + '</td>' +
            '<td class="s">' + nguyen(x.n) + '</td><td class="s">' + tien(x.t) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }

    /* Lấy sổ mã có sẵn — anh Thắng đã khai một bộ mã bên plugin Sao Kê rồi, gõ lại lần hai là
       tạo ra hai sổ, mà hai sổ thì có ngày lệch nhau. */
    h += '<h3 class="tieu-nho">Lấy sổ mã có sẵn</h3>' +
      '<div class="chu-them">Site này có thể đã khai mã nộp tiền ở chỗ khác. Chọn sổ, xem em ghép ' +
      'thử, sửa chỗ nào lệch rồi mới lưu — em chỉ <b>đề nghị</b>, không tự ghi.</div>' +
      '<div id="dsMaNguon" style="margin-top:10px"><button class="nut" type="button" id="dsTimMa">' +
      'Tìm sổ mã trong site</button></div>';

    /* Bảng khai — bày theo CƠ SỞ, y như bảng "Mã nộp tiền" nhà mình đang dùng. */
    var theo = r.theo_co_so || {};
    h += '<h3 class="tieu-nho">Mã nộp tiền của từng cơ sở</h3>' +
      '<div class="chu-them">Người nộp gõ mã này vào nội dung chuyển khoản (<code class="nd">' +
      '… ND IBFT VC Bien Hoa KH705MTDMN0023</code>) — hệ đọc mã là biết tiền của quán nào. ' +
      'Một cơ sở khai được <b>nhiều mã</b>, cách nhau dấu phẩy: đổi mã giữa chừng thì mã cũ vẫn ' +
      'nhận ra, sao kê mấy tháng trước không hoá thành "chưa gán". Khai được cả mẩu chữ thường ' +
      '(ví dụ <code class="nd">tutu tan phu</code>) cho những khoản người ta quên gõ mã.</div>' +
      '<div class="bang-cuon" style="margin-top:10px"><table id="dtBankBang"><thead><tr>' +
        '<th style="text-align:left">Cơ sở</th><th style="text-align:left">Mã nộp tiền</th>' +
      '</tr></thead><tbody>' +
      ch.map(function (t) {
        return '<tr><td style="text-align:left">' + esc(t) + '</td>' +
          '<td style="text-align:left"><input type="text" data-ch-ma="' + esc(t) + '" style="width:100%" ' +
          'value="' + esc((theo[t] || []).join(', ')) + '" placeholder="VD: KH705MTDMN0023"></td></tr>';
      }).join('') +
      '</tbody></table></div>' +
      '<div style="margin-top:12px">' +
        '<span class="o"><label for="dtGioCat">Giờ cắt</label>' +
          '<input type="number" id="dtGioCat" min="0" max="23" step="1" style="width:66px" value="' +
          esc(String(r.gio_cat)) + '"></span> ' +
        '<span class="chu-them">Tiền vào trước giờ này tính cho doanh thu <b>hôm trước</b> — ' +
          'quán đóng cửa đêm, sáng hôm sau mới mang tiền ra ngân hàng.</span>' +
      '</div>' +
      '<div style="margin-top:12px"><button class="nut chinh" type="button" id="dtLuuBank">Lưu và gán lại</button> ' +
        '<span id="dtBankBao" class="chu-them"></span></div></div>';

    o.insertAdjacentHTML('beforeend', h);

    /* Mẫu dòng của sổ đang chọn */
    var oMau = o.querySelector('#dtNguonMau'), oChon = o.querySelector('#dtNguon');
    function veMau() {
      if (!oMau) return;
      var x = null;
      ng.forEach(function (n) { if (!oChon || n.bang === oChon.value) { if (!x) x = n; } });
      if (!x || !(x.mau || []).length) { oMau.innerHTML = ''; return; }
      oMau.innerHTML = 'Hai dòng gần nhất trong sổ này: ' + x.mau.map(function (m) {
        return '<div style="margin-top:4px"><code class="nd">' + esc(m.ngay) + ' · ' + tien(m.tien) +
          ' · ' + esc(m.nd) + '</code></div>';
      }).join('');
    }
    if (oChon) oChon.addEventListener('change', veMau);
    veMau();

    var nutTay = o.querySelector('#dtNguonTay');
    if (nutTay) nutTay.addEventListener('click', function () { chonBangTay(o, 'bank'); });
    var nutMomo = o.querySelector('#dtNguonMomo');
    if (nutMomo) nutMomo.addEventListener('click', function () { chonBangTay(o, 'momo'); });

    var nutKeo = o.querySelector('#dtKeoSk');
    if (nutKeo) {
      nutKeo.addEventListener('click', function () {
        nutKeo.disabled = true; nutKeo.textContent = 'Đang kéo…';
        var fd = new FormData();
        var tk = o.querySelector('#dtTuKeo');
        var sn = o.querySelector('#dtNguon');
        if (tk && tk.checked) fd.append('tu_dong', '1');
        if (sn && sn.value) fd.append('bang', sn.value);
        api('sao-ke-keo', { method: 'POST', body: fd }).then(function (kq) {
          window.alert(keChuyenKeo(kq.vua_keo || {}, ''));
          taiQuanTri();
        }).catch(function (e) {
          nutKeo.disabled = false; nutKeo.textContent = 'Kéo giao dịch về';
          window.alert(e.message || e);
        });
      });
    }

    var nutTim = o.querySelector('#dsTimMa');
    if (nutTim) nutTim.addEventListener('click', function () { timSoMa(o, ''); });

    var bang = o.querySelector('#dtBankBang tbody');

    /* Chọn cơ sở ngay ở dòng "mã lạ" là mã ấy nhảy vào ô của cơ sở đó — khỏi cuộn xuống gõ lại. */
    Array.prototype.forEach.call(o.querySelectorAll('[data-ma-la]'), function (se) {
      se.addEventListener('change', function () {
        if (!se.value) return;
        var o_ma = bang.querySelector('[data-ch-ma="' + se.value.replace(/"/g, '\\"') + '"]');
        if (!o_ma) return;
        var cu = o_ma.value.trim();
        var ma = se.dataset.maLa;
        if (cu.split(/[,;]\s*/).indexOf(ma) < 0) o_ma.value = cu ? cu + ', ' + ma : ma;
        o_ma.style.outline = '2px solid var(--app)';
        o_ma.scrollIntoView({ block: 'center' });
        setTimeout(function () { o_ma.style.outline = ''; }, 1500);
      });
    });

    o.querySelector('#dtLuuBank').addEventListener('click', function () {
      var ds = {};
      Array.prototype.forEach.call(bang.querySelectorAll('[data-ch-ma]'), function (i) {
        var v = i.value.trim();
        if (v) ds[i.dataset.chMa] = v;
      });
      var nut = o.querySelector('#dtLuuBank');
      nut.disabled = true; nut.textContent = 'Đang gán lại…';
      var fd = new FormData();
      fd.append('ghep', JSON.stringify(ds));
      fd.append('gio_cat', o.querySelector('#dtGioCat').value);
      api('sao-ke-ghep', { method: 'POST', body: fd }).then(function (kq) {
        nut.disabled = false; nut.textContent = 'Lưu và gán lại';
        o.querySelector('#dtBankBao').textContent = 'Đã gán lại ' + nguyen(kq.da_gan_lai) + ' khoản. ' +
          ((kq.chua_gan || []).length ? 'Còn ' + (kq.chua_gan || []).length + ' khoản chưa nhận ra cơ sở.' : 'Không còn khoản nào lạc.');
        taiQuanTri();
      }).catch(function (e) {
        nut.disabled = false; nut.textContent = 'Lưu và gán lại';
        o.querySelector('#dtBankBao').textContent = e.message || e;
      });
    });
  }

  /* Kể lại một lượt kéo cho ra chuyện — nhất là khi kéo về 0 khoản, vì đó là lúc người ta cần
     biết VÌ SAO chứ không phải một con số 0. */
  function keChuyenKeo(v, bang) {
    var d = [];
    d.push('Đã mang về ' + nguyen(v.keo || 0) + ' khoản tiền vào' + (bang ? ' từ ' + bang : '') + '.');
    if (v.bo_qr) d.push('Bỏ ' + nguyen(v.bo_qr) + ' khoản tiền cổng QR (VietQR / MoMo / VNPAY).');
    if (v.bo_ghe) d.push('Bỏ ' + nguyen(v.bo_ghe) + ' khoản là tiền khách trả ghế.');
    if (v.bo_ra) d.push('Bỏ ' + nguyen(v.bo_ra) + ' dòng tiền đi hoặc chưa thành công.');
    if (!v.keo && v.bo_qr) {
      d.push('');
      d.push('⚠️ Sổ này toàn tiền CỔNG QR — đó là khách quét mã trả tiền, tự về tài khoản, không ' +
        'ai phải mang đi nộp. Sổ cần tìm là sổ SAO KÊ NGÂN HÀNG (nộp trực tiếp).');
    } else if (!v.keo) {
      d.push('');
      d.push('⚠️ Không có khoản nào hợp lệ. Kiểm lại cột Ngày / Số tiền / Hướng đã chỉ đúng chưa.');
    }
    if (v.chua_gan && v.chua_gan.so_dong) {
      d.push('Còn ' + nguyen(v.chua_gan.so_dong) + ' khoản chưa nhận ra cơ sở — khai mã bên dưới.');
    }
    return d.join('\n');
  }

  /* ---- chọn tay: bảng nào cũng được, cột nào là gì thì tự khai ----
     Máy dò bằng tên cột, mà tên cột là thứ người khác đặt. Đoán được thì tốt; đoán không được
     thì phải để người chỉ, chứ không bắt người ta chờ mình đoán đúng. */
  function chonBangTay(o, viec) {
    var noi = o.querySelector('#dtNguonTayNoi');
    noi.hidden = false;
    noi.innerHTML = '<div class="chu-them">Đang đọc danh sách bảng…</div>';
    api('moi-bang').then(function (r) {
      var ds = (r.bang_ds || []).filter(function (x) { return x.uoc > 0; });
      noi.innerHTML =
        (viec === 'momo'
          ? '<div class="chu-them" style="margin-bottom:8px"><b>Sổ MoMo</b> — để biết phần khách trả ' +
            'qua MoMo mà máy POS ghi có khớp với số MoMo nhận không. MoMo không bắn webhook nên sổ ' +
            'ấy do người ta tải file lên; ngày nào chưa tải thì sổ thiếu ngày đó, và thiếu file ' +
            'khác hẳn với "MoMo giữ tiền".<br><b>Sổ cổng thường gộp cả VietQR, MoMo và VNPAY trong ' +
            'một bảng</b> — nhớ dùng ô <i>Chỉ lấy những dòng có</i> bên dưới để lọc đúng nguồn MoMo, ' +
            'không thì tiền VietQR của cả chuỗi cộng nhầm vào phần MoMo.</div>'
          : '') +
        (viec === 'momo'
          ? '<div style="margin-bottom:10px"><button class="nut chinh" type="button" id="dtTimMomo">' +
            'Tự tìm giao dịch MoMo trong site</button> <span class="chu-them">Không bảng nào tên ' +
            '"momo" cả — nếu có thì MoMo nằm lẫn trong sổ cổng, nhận ra bằng một giá trị trong cột ' +
            'nguồn. Bấm cái này để em dò hộ, khỏi mở từng bảng.</span>' +
            '<div id="dtTimMomoNoi" style="margin-top:10px"></div>' +
            '<div style="margin-top:14px"><b class="tieu-nho">File MoMo có sẵn trên máy chủ</b>' +
            '<div class="chu-them">Anh đã tải <code>Transaction_report_….csv</code> lên site rồi thì ' +
            '<b>không phải tải lần nữa</b>. Em dò trong thư mục tải lên, thấy file nào thì hút thẳng ' +
            'về kho MoMo. Nạp lại đúng file cũ cũng không sao — khoá theo mã giao dịch nên ghi đè, ' +
            'không cộng dồn.</div>' +
            '<div style="margin-top:8px"><button class="nut" type="button" id="dtFileMomo">' +
            'Tìm file MoMo trên máy chủ</button></div>' +
            '<div id="dtFileMomoNoi" style="margin-top:10px"></div></div></div>'
          : '') +
        '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">' +
          '<select id="dtBangTay">' + ds.map(function (x) {
            return '<option value="' + esc(x.bang) + '">' + esc(x.bang) + ' — khoảng ' +
              nguyen(x.uoc) + ' dòng</option>';
          }).join('') + '</select>' +
          '<button class="nut" type="button" id="dtSoiBang">Xem bảng này</button>' +
        '</div><div id="dtSoiNoi" style="margin-top:10px"></div>';
      noi.querySelector('#dtSoiBang').addEventListener('click', function () {
        S.locGoi = null;
        soiBang(o, noi.querySelector('#dtBangTay').value, viec);
      });
      var nTim = noi.querySelector('#dtTimMomo');
      if (nTim) nTim.addEventListener('click', function () { timMomo(o, noi, viec); });
      var nFile = noi.querySelector('#dtFileMomo');
      if (nFile) nFile.addEventListener('click', function () { timFileMomo(o, noi); });
    }).catch(function (e) {
      noi.innerHTML = '<div class="chu-them">' + esc(e.message || e) + '</div>';
    });
  }

  /**
   * DÒ XEM SITE CÓ SỔ NÀO CHỨA GIAO DỊCH MOMO KHÔNG.
   *
   * 🔴 ANH THẮNG MỞ DANH SÁCH BẢNG RA VÀ KHÔNG THẤY CÁI NÀO LÀ MOMO. Đúng — chẳng có bảng nào tên
   *    "momo". MoMo nếu có thì nằm LẪN trong sổ cổng, phân biệt bằng một giá trị trong cột nguồn.
   *    Bắt người ta mở hơn hai chục bảng rồi dò từng cột là bắt làm việc của máy.
   *
   *    Và khi dò xong mà KHÔNG CÓ thì phải nói thẳng là không có, kèm đường đi thật (nạp file
   *    sao kê MoMo) — chứ để màn im lặng là người ta còn đi tìm tiếp một thứ không tồn tại.
   */
  function timMomo(o, noi, viec) {
    var hop = noi.querySelector('#dtTimMomoNoi');
    var nut = noi.querySelector('#dtTimMomo');
    if (!hop) return;
    nut.disabled = true;
    hop.innerHTML = '<div class="chu-them">Đang dò từng sổ trong site…</div>';
    api('tim-momo').then(function (r) {
      nut.disabled = false;
      var thay = r.thay || [];
      if (!thay.length) {
        hop.innerHTML = '<div class="canh-ghep">Đã dò <b>' + nguyen(r.so_bang) + ' sổ</b> trong site: ' +
          '<b>không sổ nào có giao dịch MoMo</b>. Nghĩa là cổng thanh toán đang về site này không ' +
          'ghi phần MoMo — không phải anh chọn nhầm bảng.<br>Đường đi đúng: bấm <b>Nạp báo cáo → ' +
          'thẻ Sao kê MoMo</b> và thả file <code>Transaction_report_….csv</code> tải từ trang MoMo. ' +
          (r.co_file ? 'Kho MoMo hiện đã có <b>' + nguyen(r.co_file) + ' giao dịch</b> — sổ MoMo ' +
            'đang chạy bằng file ấy, anh không cần khai bảng nào nữa.' : '') + '</div>';
        return;
      }
      hop.innerHTML = '<div class="chu-them">Dò <b>' + nguyen(r.so_bang) + ' sổ</b>, thấy ' +
          nguyen(thay.length) + ' chỗ có chữ "momo":</div>' +
        '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          '<th style="text-align:left">Sổ</th><th style="text-align:left">Cột</th>' +
          '<th style="text-align:left">Giá trị</th><th>Số dòng</th><th></th>' +
        '</tr></thead><tbody>' +
        thay.map(function (x, i) {
          return '<tr><td style="text-align:left"><code class="nd">' + esc(x.bang) + '</code></td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.cot) + '</code></td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.gt) + '</code></td>' +
            '<td class="s">' + nguyen(x.n) + '</td>' +
            '<td><button class="nut chinh" type="button" data-momo-ngay="' + i + '">Khai ngay</button> ' +
            '<button class="nut" type="button" data-momo="' + i + '">Xem rồi khai →</button></td></tr>';
        }).join('') + '</tbody></table></div>';
      /* Một chạm: lấy cột hệ đoán rồi khai luôn. Màn khai tay vẫn còn cho ai muốn xem trước —
         nhưng bắt người ta đi qua nó chỉ để bấm đúng cái nút cuối là bắt làm việc thừa. */
      Array.prototype.forEach.call(hop.querySelectorAll('[data-momo-ngay]'), function (b) {
        b.addEventListener('click', function () {
          var x = thay[parseInt(b.dataset.momoNgay, 10)];
          b.disabled = true; b.textContent = 'Đang khai…';
          api('soi-bang?bang=' + encodeURIComponent(x.bang) +
              '&cot_them=' + encodeURIComponent(x.cot)).then(function (sb) {
            var doan = sb.doan || {};
            if (!doan.ngay || !doan.so_tien || (!doan.nhan && !doan.noi_dung)) {
              b.disabled = false; b.textContent = 'Khai ngay';
              S.locGoi = { cot: x.cot, gt: x.gt };
              soiBang(o, x.bang, viec);
              window.alert('Sổ này em chưa đoán chắc được cột nào là cột nào — anh chỉ giúp em ở ' +
                'bảng vừa mở bên dưới.');
              return;
            }
            var fd = new FormData();
            fd.append('bang', x.bang);
            fd.append('cot', JSON.stringify(doan));
            fd.append('loc_cot', x.cot);
            fd.append('loc_gt', x.gt);
            return api('nguon-momo', { method: 'POST', body: fd }).then(function (kq) {
              window.alert('Đã khai sổ MoMo: ' + x.bang + ' — chỉ lấy dòng có ' + x.cot + ' = ' + x.gt +
                '.\nNgày lấy từ cột ' + doan.ngay + ', tiền từ ' + doan.so_tien +
                ', tên cửa hàng từ ' + (doan.nhan || doan.noi_dung) + '.' +
                (kq.cot && kq.cot.dem
                  ? '\n\nSổ này GỘP THEO NGÀY (cột đếm ' + kq.cot.dem + ') nên nó cho tổng ngày × ' +
                    'cơ sở. Muốn đối soát tới từng giao dịch thì cần file thô — xem khối "File MoMo ' +
                    'có sẵn trên máy chủ" ngay dưới.'
                  : ''));
              taiQuanTri();
            });
          }).catch(function (e) {
            b.disabled = false; b.textContent = 'Khai ngay';
            window.alert(e.message || e);
          });
        });
      });
      Array.prototype.forEach.call(hop.querySelectorAll('[data-momo]'), function (b) {
        b.addEventListener('click', function () {
          var x = thay[parseInt(b.dataset.momo, 10)];
          var se = noi.querySelector('#dtBangTay');
          if (se) se.value = x.bang;
          /* Chuyển sẵn cả bộ lọc sang màn khai — đây chính là chỗ dễ sai nhất: chọn đúng bảng mà
             quên lọc nguồn là tiền VietQR của cả chuỗi cộng nhầm vào phần MoMo. */
          S.locGoi = { cot: x.cot, gt: x.gt };
          soiBang(o, x.bang, viec);
        });
      });
    }).catch(function (e) {
      nut.disabled = false;
      hop.innerHTML = '<div class="chu-them">' + esc(e.message || e) + '</div>';
    });
  }

  /**
   * DÒ FILE MOMO CÒN NẰM TRÊN MÁY CHỦ, ĐỂ KHỎI TẢI LÊN LẦN HAI.
   *
   * 🔴 ANH THẮNG HỎI: "NẠP TRÊN SAO KÊ RỒI, SAO PHẢI NẠP LẠI LẦN 2 TRÊN FABi?" — đúng là không
   *    nên. Plugin Sao Kê nhận file rồi chỉ giữ bản GỘP theo ngày, vứt từng giao dịch đi, nên bên
   *    này không có gì để đối soát tới từng mã. Nhưng nếu file gốc còn trong thư mục tải lên thì
   *    lấy về là xong — bắt tải lên lần nữa cùng một file mới là việc thừa.
   */
  function timFileMomo(o, noi) {
    var hop = noi.querySelector('#dtFileMomoNoi');
    var nut = noi.querySelector('#dtFileMomo');
    if (!hop) return;
    nut.disabled = true;
    hop.innerHTML = '<div class="chu-them">Đang dò thư mục tải lên…</div>';
    api('momo-file').then(function (r) {
      nut.disabled = false;
      var ds = r.file_ds || [];
      if (!ds.length) {
        hop.innerHTML = '<div class="canh-ghep">Không thấy file <code>Transaction_report_….csv</code> ' +
          'nào còn trên máy chủ — plugin Sao Kê đọc xong thì xoá file đi, chỉ giữ bản gộp. ' +
          'Vậy file thô phải qua đây một lần: <b>Nạp báo cáo → thẻ Sao kê MoMo</b>. ' +
          'Mỗi file chỉ nạp một lần, không phải nạp lại hàng ngày.' +
          (r.da_co ? '<br>Kho MoMo hiện đã có <b>' + nguyen(r.da_co) + ' giao dịch</b>.' : '') + '</div>';
        return;
      }
      hop.innerHTML = '<div class="chu-them">Thấy <b>' + nguyen(ds.length) + ' file</b>:</div>' +
        '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr>' +
          '<th style="text-align:left"><input type="checkbox" id="dtFileHet" checked></th>' +
          '<th style="text-align:left">File</th><th>Nặng</th><th>Sửa lúc</th>' +
        '</tr></thead><tbody>' +
        ds.map(function (f) {
          return '<tr><td><input type="checkbox" data-file="' + esc(f.ten) + '" checked></td>' +
            '<td style="text-align:left"><code class="nd">' + esc(f.ten) + '</code></td>' +
            '<td class="s">' + nguyen(Math.round(f.co / 1024)) + ' KB</td>' +
            '<td class="s">' + esc(f.luc) + '</td></tr>';
        }).join('') + '</tbody></table></div>' +
        '<div style="margin-top:10px"><button class="nut chinh" type="button" id="dtHutMomo">' +
        'Hút những file đã chọn về kho</button></div>';
      var het = hop.querySelector('#dtFileHet');
      het.addEventListener('change', function () {
        Array.prototype.forEach.call(hop.querySelectorAll('[data-file]'), function (c) {
          c.checked = het.checked;
        });
      });
      hop.querySelector('#dtHutMomo').addEventListener('click', function () {
        var ten = [];
        Array.prototype.forEach.call(hop.querySelectorAll('[data-file]'), function (c) {
          if (c.checked) ten.push(c.dataset.file);
        });
        if (!ten.length) { window.alert('Chưa chọn file nào.'); return; }
        var b = hop.querySelector('#dtHutMomo');
        b.disabled = true; b.textContent = 'Đang hút ' + ten.length + ' file…';
        var fd = new FormData();
        fd.append('ten', JSON.stringify(ten));
        api('momo-hut', { method: 'POST', body: fd }).then(function (kq) {
          window.alert('Đã hút ' + nguyen(kq.so_file) + ' file · ghi ' + nguyen(kq.da_ghi) +
            ' giao dịch vào kho MoMo.' +
            (kq.hoc ? '\nĐã học ' + nguyen(kq.hoc) + ' mã cửa hàng MoMo ứng với quán nào.' : '') +
            ((kq.lan_can || []).length
              ? '\n⚠️ ' + kq.lan_can.length + ' mã cửa hàng MoMo trỏ về HAI quán trong cùng kỳ — ' +
                'đúng cảnh máy vừa dời cơ sở. Hệ không đoán.'
              : '') +
            ((kq.hong || []).length
              ? '\n\nĐọc không được ' + kq.hong.length + ' file:\n' +
                kq.hong.map(function (h) { return '· ' + h.ten + ': ' + h.vi; }).join('\n')
              : ''));
          taiQuanTri();
        }).catch(function (e) {
          b.disabled = false; b.textContent = 'Hút những file đã chọn về kho';
          window.alert(e.message || e);
        });
      });
    }).catch(function (e) {
      nut.disabled = false;
      hop.innerHTML = '<div class="chu-them">' + esc(e.message || e) + '</div>';
    });
  }

  function soiBang(o, bang, viec) {
    var noi = o.querySelector('#dtSoiNoi');
    noi.innerHTML = '<div class="chu-them">Đang đọc…</div>';
    api('soi-bang?bang=' + encodeURIComponent(bang) +
        (S.locGoi ? '&cot_them=' + encodeURIComponent(S.locGoi.cot) : '')).then(function (r) {
      var cot = r.cot || [], dong = r.dong || [], doan = r.doan || {};
      if (!cot.length) { noi.innerHTML = '<div class="chu-them">Bảng rỗng hoặc không đọc được.</div>'; return; }
      var vai = viec === 'momo'
        ? [['ngay', 'Ngày giao dịch *'], ['so_tien', 'Số tiền *'], ['nhan', 'Tên cửa hàng *'],
           ['noi_dung', 'Nội dung / mã cửa hàng'], ['dem', 'Cột đếm giao dịch (nếu sổ gộp theo ngày)']]
        : [['ngay', 'Ngày giao dịch *'], ['so_tien', 'Số tiền vào *'], ['noi_dung', 'Nội dung'],
           ['ma_gd', 'Mã giao dịch'], ['tai_khoan', 'Số tài khoản'], ['nhan', 'Nhãn phân loại'],
           ['tien_ra', 'Tiền ra'], ['nguon', 'Nguồn / loại'],
           ['huong', 'Hướng (Đến / Đi)'], ['trang_thai', 'Trạng thái']];
      var gtri = r.gia_tri || {};
      var h = '<div class="bang-cuon"><table><thead><tr>' +
        cot.map(function (c) { return '<th style="text-align:left">' + esc(c) + '</th>'; }).join('') +
        '</tr></thead><tbody>' +
        dong.map(function (d) {
          return '<tr>' + cot.map(function (c) {
            return '<td style="text-align:left"><code class="nd">' + esc(d[c] == null ? '' : d[c]) + '</code></td>';
          }).join('') + '</tr>';
        }).join('') + '</tbody></table></div>' +
        '<div class="bc-luoi" style="margin-top:12px">' +
        vai.map(function (v) {
          return '<label class="bc-o"><b>' + esc(v[1]) + '</b><select data-cot="' + v[0] + '">' +
            '<option value="">— không có —</option>' +
            cot.map(function (c) {
              return '<option value="' + esc(c) + '"' + (doan[v[0]] === c ? ' selected' : '') + '>' + esc(c) + '</option>';
            }).join('') + '</select></label>';
        }).join('') + '</div>' +
        /* Sổ cổng gộp cả VietQR / MoMo / VNPAY trong một bảng — phải lọc đúng nguồn, không thì
           tiền cổng khác cộng nhầm vào phần đang xét. */
        (Object.keys(gtri).length
          ? '<div style="margin-top:12px"><b class="tieu-nho">Chỉ lấy những dòng có</b>' +
            '<div class="bc-luoi" style="margin-top:6px">' +
            '<label class="bc-o"><b>Cột</b><select id="dtLocCot"><option value="">— lấy tất cả —</option>' +
            Object.keys(gtri).map(function (c) {
              return '<option value="' + esc(c) + '">' + esc(c) + '</option>';
            }).join('') + '</select></label>' +
            '<label class="bc-o"><b>Bằng giá trị</b><select id="dtLocGt"><option value="">—</option></select></label>' +
            '</div></div>'
          : '') +
        '<div style="margin-top:12px"><button class="nut chinh" type="button" id="dtKeoTay">' +
        (viec === 'momo' ? 'Dùng sổ MoMo này' : 'Dùng sổ này và kéo về') +
        '</button> <span class="chu-them">' +
        (viec === 'momo' ? 'Ngày, Số tiền và Tên cửa hàng là bắt buộc.' : 'Ngày và Số tiền là bắt buộc.') +
        '</span></div>';
      noi.innerHTML = h;

      var oCot = noi.querySelector('#dtLocCot'), oGt = noi.querySelector('#dtLocGt');
      function veGiaTri() {
        var ds = gtri[oCot.value] || [];
        oGt.innerHTML = '<option value="">—</option>' + ds.map(function (v) {
          return '<option value="' + esc(v.gt) + '">' + esc(v.gt || '(trống)') + ' — ' +
            nguyen(v.n) + ' dòng</option>';
        }).join('');
      }
      if (oCot) {
        oCot.addEventListener('change', veGiaTri);
        /* Vào đây từ nút "Tự tìm giao dịch MoMo" thì bộ lọc đã biết rồi — điền sẵn, đừng bắt
           người ta chọn lại đúng cái vừa chỉ cho họ. */
        if (S.locGoi && gtri[S.locGoi.cot]) {
          oCot.value = S.locGoi.cot;
          veGiaTri();
          oGt.value = S.locGoi.gt;
        }
        S.locGoi = null;
      }

      noi.querySelector('#dtKeoTay').addEventListener('click', function () {
        var map = {};
        Array.prototype.forEach.call(noi.querySelectorAll('[data-cot]'), function (se) {
          if (se.value) map[se.dataset.cot] = se.value;
        });
        if (!map.ngay || !map.so_tien) { window.alert('Phải chỉ cột Ngày và cột Số tiền.'); return; }
        if (viec === 'momo' && !map.nhan && !map.noi_dung) {
          window.alert('Phải chỉ cột Tên cửa hàng — để biết tiền ấy của cơ sở nào.');
          return;
        }
        var fd = new FormData();
        fd.append('bang', bang);
        fd.append('cot', JSON.stringify(map));
        var b = noi.querySelector('#dtKeoTay');
        b.disabled = true; b.textContent = 'Đang lưu…';
        if (oCot && oCot.value && oGt && oGt.value) {
          fd.append('loc_cot', oCot.value);
          fd.append('loc_gt', oGt.value);
        }
        if (viec === 'momo') {
          /* Sổ MoMo KHÔNG kéo vào kho: đọc thẳng mỗi lần xem báo cáo. Nó là sổ đối chiếu, không
             phải sổ tiền nộp — nhập nó vào kho là lẫn hai dòng tiền với nhau. */
          api('nguon-momo', { method: 'POST', body: fd }).then(function (kq) {
            window.alert('Đã khai sổ MoMo' +
              ((kq.loc && kq.loc.cot) ? ' — chỉ lấy dòng có ' + kq.loc.cot + ' = ' + kq.loc.gt : '') +
              '.\nBảng Tổng hợp cả kỳ nay có thêm cột MoMo và cột Lệch MoMo.' +
              (map.dem
                ? '\n\nSổ này GỘP THEO NGÀY (có cột đếm giao dịch ' + map.dem + '), nên nó cho ' +
                  'tổng ngày × cơ sở chứ không đối soát được từng giao dịch. Muốn tới từng mã thì ' +
                  'nạp file Transaction_report_….csv ở thẻ Sao kê MoMo — cùng file ấy, chưa gộp.'
                : ''));
            taiQuanTri();
          }).catch(function (e) {
            b.disabled = false; b.textContent = 'Dùng sổ MoMo này';
            window.alert(e.message || e);
          });
          return;
        }
        fd.append('tu_dong', '1');
        b.textContent = 'Đang kéo…';
        api('sao-ke-keo', { method: 'POST', body: fd }).then(function (kq) {
          window.alert(keChuyenKeo(kq.vua_keo || {}, bang));
          taiQuanTri();
        }).catch(function (e) {
          b.disabled = false; b.textContent = 'Dùng sổ này và kéo về';
          window.alert(e.message || e);
        });
      });
    }).catch(function (e) {
      noi.innerHTML = '<div class="chu-them">' + esc(e.message || e) + '</div>';
    });
  }

  /* Tìm và bày sổ mã có sẵn trong site, kèm đề nghị ghép tên. */
  function timSoMa(o, bang) {
    var noi = o.querySelector('#dsMaNguon');
    noi.innerHTML = '<div class="chu-them">Đang tìm…</div>';
    api('ma-nguon' + (bang ? '?bang=' + encodeURIComponent(bang) : '')).then(function (r) {
      var ds = r.nguon_ds || [], de = r.de_nghi || [], ch = r.cua_hang || [];
      if (!ds.length) {
        noi.innerHTML = '<div class="chu-them">Không thấy sổ mã nào trong site — khai tay ở bảng dưới.</div>';
        return;
      }
      var h = '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">' +
        '<select id="dsMaBang">' + ds.map(function (x) {
          return '<option value="' + esc(x.bang) + '"' + (x.bang === r.bang ? ' selected' : '') + '>' +
            esc(x.bang) + ' — ' + nguyen(x.so_ma) + ' mã</option>';
        }).join('') + '</select>' +
        '<button class="nut" type="button" id="dsXemMa">Xem thử</button></div>';
      if (de.length) {
        h += '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
          '<th style="text-align:left">Tên bên sổ kia</th><th style="text-align:left">Mã</th>' +
          '<th style="text-align:left">Ghép vào cơ sở POS</th></tr></thead><tbody>' +
          de.map(function (x) {
            return '<tr><td style="text-align:left">' + esc(x.ten) +
              (x.goi_y ? '' : '<span class="nho xau" style="display:block">chưa đoán ra — chọn tay</span>') +
              '</td><td style="text-align:left"><code class="nd">' + esc(x.ma) + '</code></td>' +
              '<td style="text-align:left"><select data-de-ma="' + esc(x.ma) + '">' +
              '<option value="">— bỏ qua —</option>' +
              ch.map(function (t) {
                return '<option value="' + esc(t) + '"' + (t === x.goi_y ? ' selected' : '') + '>' + esc(t) + '</option>';
              }).join('') + '</select></td></tr>';
          }).join('') + '</tbody></table></div>' +
          '<div style="margin-top:10px"><button class="nut chinh" type="button" id="dsNhanMa">' +
          'Điền ' + de.length + ' mã này vào bảng dưới</button> ' +
          '<span class="chu-them">Điền xong vẫn phải bấm <b>Lưu và gán lại</b>.</span></div>';
      }
      noi.innerHTML = h;
      noi.querySelector('#dsXemMa').addEventListener('click', function () {
        timSoMa(o, noi.querySelector('#dsMaBang').value);
      });
      var nhan = noi.querySelector('#dsNhanMa');
      if (nhan) {
        nhan.addEventListener('click', function () {
          var so = 0;
          Array.prototype.forEach.call(noi.querySelectorAll('[data-de-ma]'), function (se) {
            if (!se.value) return;
            var i = o.querySelector('[data-ch-ma="' + se.value.replace(/"/g, '\\"') + '"]');
            if (!i) return;
            var cu = i.value.trim(), ma = se.dataset.deMa;
            if (cu.split(/[,;]\s*/).indexOf(ma) < 0) { i.value = cu ? cu + ', ' + ma : ma; so++; }
          });
          window.alert('Đã điền ' + so + ' mã vào bảng bên dưới. Kiểm lại rồi bấm "Lưu và gán lại".');
          var b = o.querySelector('#dtBankBang');
          if (b) b.scrollIntoView({ block: 'start' });
        });
      }
    }).catch(function (e) {
      noi.innerHTML = '<div class="chu-them">' + esc(e.message || e) + '</div>';
    });
  }

  /* ---- ghép MÃ cơ sở (bên nhân sự) ↔ TÊN cơ sở (bên máy POS) ----
     Hai hệ gọi cùng một cái quán bằng hai cái tên: nhân sự ghi "FZ_SC_VIVO_T4", máy POS ghi
     "FUNZONE - Vivo City (...)". Không ai đoán hộ được, nên khai một lần ở đây. */
  function veGhep(o, r) {
    var ghep = r.ghep || [], ch = r.cua_hang || [], ng = r.nguoi || [], thieu = r.chua_ghep || [], lech = r.ten_lech || [];
    /* So tên LỎNG (bỏ khoảng trắng thừa, không phân biệt hoa thường) chỉ để BÀY ô tích; lưu lại là máy
       chủ đổi về đúng nguyên văn tên POS. 24/09/2026 anh Thắng: "tại sao có cơ sở không thêm được". */
    var long_ = function (t) { return String(t || '').replace(/[\s\u00a0]+/g, ' ').trim().toLowerCase(); };
    var h = '<div class="khung" id="dtGhep"><header><h2>Ghép cơ sở</h2>' +
      '<span class="goi">' + ghep.length + ' mã</span></header>';
    h += '<div class="chu-them" style="margin-top:6px">Mã bên trái là cơ sở trong sổ nhân sự; ' +
      'tích bên phải là những quán của mã ấy trong số liệu máy POS. <b>Tích được nhiều quán</b>: ' +
      'một điểm bán bên nhân sự có thể là hai quán trên máy POS (khu vui chơi và quán cà phê ' +
      'cùng một chỗ), người của mã ấy nhập báo cáo cho cả những quán đã tích. Chưa tích gì thì ' +
      'họ đăng nhập được nhưng không thấy số nào. Người vai <b>duyệt</b> (kế toán, quản lý) xem ' +
      'tổng mọi cơ sở nên mã của họ không cần tích. Còn muốn một người coi hai điểm bán KHÁC ' +
      'nhau thì tích thêm cơ sở cho họ ở trang Nhân sự — bên này tự theo.</div>';
    if (thieu.length) {
      h += '<div class="canh-ghep">Đang có người ở ' + thieu.length + ' mã chưa ghép: <b>' +
        thieu.map(esc).join(', ') + '</b></div>';
    }
    if (lech.length) {
      h += '<div class="canh-ghep"><b>' + lech.length + ' tên đã lưu không khớp nguyên văn tên trên máy POS</b> ' +
        '(thường do khoảng trắng thừa trong tên FABi) — người ở mã ấy sẽ không thấy quán. Bấm <b>Lưu bảng ghép</b> một lần là ' +
        'hệ tự đổi về đúng tên POS: ' + lech.map(function (x) {
          return '<code>' + esc(x.ma) + '</code> "' + esc(x.ten) + '"' + (x.goi_y !== x.ten ? ' → "' + esc(x.goi_y) + '"' : ' (chưa có quán này trong số liệu)');
        }).join('; ') + '</div>';
    }
    if (!ghep.length) {
      h += '<div class="trong">Chưa có ai được đẩy sang. Vào trang Nhân sự, cột ' +
        '<b>Quản trị báo cáo cơ sở</b>, bấm Đẩy cho cửa hàng trưởng.</div>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr><th>Mã cơ sở (nhân sự)</th>' +
        '<th>Tên cơ sở trên máy POS — tích được nhiều quán</th><th>Người</th></tr></thead><tbody>' +
        ghep.map(function (g) {
          /* Một người có thể phụ trách hai cơ sở nên họ hiện ở cả hai hàng. */
          var nguoi = ng.filter(function (x) { return (x.coso_ds || []).indexOf(g.ma) >= 0; });
          var chiDuyet = nguoi.length && nguoi.every(function (x) { return x.vai === 'duyet'; });
          var chon = g.ten_ds || [];
          return '<tr data-ma="' + esc(g.ma) + '">' +
            '<td><code>' + esc(g.ma) + '</code></td>' +
            /* TÍCH, KHÔNG PHẢI CHỌN MỘT. Một điểm bán trên sổ nhân sự có thể là hai quán trên
               máy POS (Gò An Lạc: khu vui chơi + quán cà phê), cùng một cửa hàng trưởng coi. */
            '<td><div class="ghep-chon' + (chiDuyet ? ' mo-nhat' : '') + '" data-o-ghep="' + esc(g.ma) + '">' +
              (chiDuyet ? '<div class="ghep-nhac">Ai cũng vai duyệt — xem tổng mọi cơ sở, không cần tích.</div>' : '') +
              ch.map(function (t) {
                var daTich = chon.indexOf(t) >= 0 || chon.some(function (c) { return long_(c) === long_(t); });
                return '<label><input type="checkbox" data-ghep="' + esc(g.ma) + '" value="' + esc(t) + '"' +
                  (daTich ? ' checked' : '') + '><span>' + esc(t) + '</span></label>';
              }).join('') +
            '</div></td>' +
            '<td style="text-align:left">' + (nguoi.length
              ? nguoi.map(function (x) {
                  return esc(x.ho_ten) + ' <span style="color:var(--ink-3)">(' + esc(x.ma_nv) +
                    (x.vai === 'duyet' ? ', duyệt — xem tổng mọi cơ sở'
                      : (!x.vai ? ', chưa cấp quyền'
                        : ((x.coso_ds || []).length > 1 ? ', ' + x.coso_ds.length + ' cơ sở' : ''))) + ')</span>';
                }).join('<br>')
              : '<span style="color:var(--ink-3)">—</span>') + '</td></tr>';
        }).join('') + '</tbody></table></div>' +
        '<div style="margin-top:12px"><button class="nut chinh" type="button" id="dtLuuGhep">Lưu bảng ghép</button> ' +
        '<span id="dtGhepBao" class="chu-them"></span></div>';
    }
    h += '</div>';
    /* Bảng Ghép cơ sở đứng NGAY SAU bảng phân quyền, không chiếm đầu tab. Anh Thắng 23/09/2026 mở
       tab Quản trị, thấy Ghép cơ sở choán cả màn và hỏi "Tab Phân Quyền bên Fabi chưa có" — bảng
       cấp vai nằm dưới, phải cuộn mới thấy. Việc làm thường (cấp vai) phải ở trên việc làm một
       lần (ghép mã). */
    var pq = o.querySelector('#dtNguoiPin');
    if (pq) pq.insertAdjacentHTML('afterend', h);
    else o.insertAdjacentHTML('afterbegin', h);

    var nut = o.querySelector('#dtLuuGhep');
    if (!nut) return;
    nut.addEventListener('click', function () {
      var bang = {};
      Array.prototype.forEach.call(o.querySelectorAll('[data-o-ghep]'), function (h) { bang[h.dataset.oGhep] = []; });
      Array.prototype.forEach.call(o.querySelectorAll('input[data-ghep]'), function (c) {
        if (c.checked) bang[c.dataset.ghep].push(c.value);
      });
      nut.disabled = true; nut.textContent = 'Đang lưu…';
      var fd = new FormData(); fd.append('ghep', JSON.stringify(bang));
      api('ghep-co-so', { method: 'POST', body: fd }).then(function () {
        nut.disabled = false; nut.textContent = 'Lưu bảng ghép';
        o.querySelector('#dtGhepBao').textContent = 'Đã lưu.';
        taiQuanTri();
      }).catch(function (e) {
        nut.disabled = false; nut.textContent = 'Lưu bảng ghép';
        o.querySelector('#dtGhepBao').textContent = e.message || e;
      });
    });
  }

  /* ---- CẤU HÌNH THEO TỪNG CỬA HÀNG: bóc tách vé → khách, và nhóm Sale vé / Sale phụ ----
     Anh Thắng 23/09/2026: *"Mỗi cửa hàng 1 cấu hình đi. Để cho dễ"* — *"trong tài khoản admin… cứ
     chọn cửa hàng để cấu hình tránh lẫn lộn"*. MỘT ô chọn cửa hàng (S.cauHinhCS) dùng chung cho cả
     hai khối; đổi quán là cả hai tải lại. Bảng chung cũ (bản 1.59.0) chỉ còn là mặc định cho quán
     chưa khai riêng. */
  function cauHinhCS() {
    var ds = (S.cf && S.cf.cua_hang) || [];
    if (!S.cauHinhCS || ds.indexOf(S.cauHinhCS) < 0) {
      /* Mặc định gian Tàu đầu tiên — "áp dụng cho gian Tàu trước". */
      var tau = ds.filter(function (t) { return /t[àa]u|train/i.test(t); });
      S.cauHinhCS = tau.length ? tau[0] : (ds[0] || '');
    }
    return S.cauHinhCS;
  }
  function oChonCS(id, r) {
    var ds = (S.cf && S.cf.cua_hang) || [];
    return '<div class="loc" style="margin:12px 0 4px"><label class="o">Cửa hàng<select id="' + id + '" data-chon-cs>' +
      ds.map(function (t) { return '<option value="' + esc(t) + '"' + (t === r.cua_hang ? ' selected' : '') + '>' + esc(t) + '</option>'; }).join('') +
      '</select></label><span class="chu-them" style="margin:0">Cấu hình khai <b>riêng cho cửa hàng này</b>. Đổi ô chọn là đổi cả hai khối bên dưới.</span></div>';
  }
  function noiChonCS(o) {
    Array.prototype.forEach.call(o.querySelectorAll('select[data-chon-cs]'), function (sel) {
      if (sel.dataset.noi) return;
      sel.dataset.noi = '1';
      sel.addEventListener('change', function () { S.cauHinhCS = sel.value; taiVeKhach(o); taiNhomVe(o); });
    });
  }

  /* Lỗi tải hai khối cấu hình PHẢI HIỆN RA. 24/09/2026 anh Thắng: "chỗ set Sale Phụ anh không thấy" —
     bản trước nuốt lỗi bằng catch rỗng, hỏng là khối biến mất không dấu vết. Người không có quyền nạp
     (403) thì đúng là không thấy, nhưng phải nói vì sao. */
  function khoiLoi(o, id, ten, e) {
    var cu = o.querySelector('#' + id); if (cu) cu.remove();
    var h = '<div class="khung" id="' + id + '"><header><h2>' + esc(ten) + '</h2></header>' +
      '<div class="trong">Không tải được: ' + esc(e && e.message ? e.message : String(e)) +
      '. Khối này chỉ mở cho người được nạp file (vai duyệt / tài khoản biên tập).</div></div>';
    var moc = o.querySelector('#dtGhep');
    if (moc) moc.insertAdjacentHTML('beforebegin', h); else o.insertAdjacentHTML('beforeend', h);
  }
  function taiVeKhach(o) {
    var cs = cauHinhCS();
    api('ve-khach?cua_hang=' + encodeURIComponent(cs)).then(function (r) {
      var cu = o.querySelector('#dtVeKhach');
      if (cu) cu.remove();
      veVeKhach(o, r);
    }).catch(function (e) { khoiLoi(o, 'dtVeKhach', 'Bóc tách vé → khách vào', e); });
  }
  function taiNhomVe(o) {
    var cs = cauHinhCS();
    api('nhom-ve?cua_hang=' + encodeURIComponent(cs)).then(function (r) { veNhomVe(o, r); })
      .catch(function (e) { khoiLoi(o, 'dtNhomVe', 'Sale vé / Bán lẻ / Sale phụ', e); });
  }

  function veVeKhach(o, r) {
    var mon = r.mon || [], bang = r.bang || {}, rieng = r.bang_rieng || {}, conThieu = r.con_thieu || {};
    var ve = mon.filter(function (x) { return x.la_ve || x.khach != null; });
    var khac = mon.filter(function (x) { return !x.la_ve && x.khach == null; });
    var chua = ve.filter(function (x) { return x.khach == null; }).length;
    var quanThieu = Object.keys(conThieu).filter(function (c) { return c !== r.cua_hang; });
    var h = '<div class="khung" id="dtVeKhach"><header><h2>Bóc tách vé → khách vào</h2>' +
      '<span class="goi">' + esc(String(r.cua_hang || '').slice(0, 34)) + ' · ' + Object.keys(rieng).length + ' vé khai riêng' +
      (chua ? ' · <b style="color:var(--xau)">' + chua + ' chưa khai</b>' : '') + '</span></header>' +
      '<div class="chu-them" style="margin-top:6px">Mỗi loại vé trên máy POS tính <b>bao nhiêu khách</b>: vé ghép ' +
      '<i>Trẻ em + Người lớn</i> là <b>2</b>, vé lẻ là <b>1</b>. Máy tự ra <b>Khách vào (POS)</b> ở tab Nhập báo cáo ' +
      'để so với số nhân viên đếm ở cửa. Vé <b>chưa khai</b> đang tạm tính 1 khách/vé và đã được <b>điền sẵn gợi ý</b> ' +
      'ở bảng dưới — sửa nếu cần rồi bấm Lưu. Ô để trống = không tính; <b>0</b> = vé không ứng với người (vé online đã gộp, vé bù…). ' +
      'Cột <b>Sale phụ mỗi vé</b>: tiền phụ của <b>riêng loại vé này</b> (combo này 20.000, combo kia 15.000) — để trống là theo ' +
      'số của nhóm món khai ở khối dưới; gõ 0 là vé này không có phụ.</div>' +
      oChonCS('vkCS', r);
    if (quanThieu.length) {
      h += '<div class="canh-ghep">Còn vé chưa khai ở ' + quanThieu.length + ' cửa hàng khác: ' +
        quanThieu.map(function (c) {
          return '<button class="vien" type="button" data-sang-cs="' + esc(c) + '" style="margin:2px">' + esc(String(c).slice(0, 30)) + ' (' + conThieu[c] + ')</button>';
        }).join(' ') + '</div>';
    }
    if (!mon.length) {
      h += '<div class="trong">Cửa hàng này chưa có món nào trong kho số FABi 90 ngày gần đây.</div>';
    } else {
      var hang = function (x) {
        var thieu = x.la_ve && x.khach == null;
        return '<tr data-ten="' + esc(x.ten) + '"' + (thieu ? ' style="background:var(--app-mem)"' : '') + '>' +
          '<td style="text-align:left">' + esc(x.ten) + '<span style="display:block;color:var(--ink-3);font-size:12px">' + esc(x.nhom || '') +
            (x.khach != null && !x.rieng ? ' · <i>thừa bảng chung</i>' : '') + (thieu ? ' · <b style="color:var(--xau)">chưa khai — điền sẵn gợi ý</b>' : '') + '</span></td>' +
          '<td class="s">' + nguyen(x.so_luong) + '</td>' +
          /* Chưa khai thì ĐIỀN SẴN gợi ý (không chỉ placeholder) để một lần Lưu là xong quán này. */
          '<td><input type="number" min="0" step="1" inputmode="numeric" data-vk="' + esc(x.ten) + '" style="width:84px" value="' +
            (x.khach != null ? x.khach : (thieu && x.goi_y != null ? x.goi_y : '')) + '" placeholder="' + (x.goi_y != null ? 'gợi ý ' + x.goi_y : '—') + '"></td>' +
          /* Sale phụ theo TÊN vé: trống = theo nhóm (placeholder cho biết nhóm đang áp bao nhiêu); gõ 0 = vé này không phụ. */
          '<td><input type="number" min="0" step="1000" inputmode="numeric" data-vp="' + esc(x.ten) + '" style="width:96px" value="' +
            (x.phu != null ? x.phu : '') + '" placeholder="' + (x.phu_nhom != null ? 'nhóm: ' + nguyen(x.phu_nhom) : '—') + '"' +
            (x.phu != null && !x.phu_rieng ? ' title="thừa bảng chung"' : '') + '></td>' +
          '</tr>';
      };
      h += '<div class="bang-cuon"><table><thead><tr><th>Món / vé</th><th>Đã bán 90 ngày</th><th>Khách mỗi vé</th><th>Sale phụ mỗi vé (đ)</th></tr></thead><tbody>' +
        ve.map(hang).join('') +
        (khac.length
          ? '<tr><td colspan="4" style="text-align:left;color:var(--ink-3)"><details><summary style="cursor:pointer">' +
            khac.length + ' món khác không phải vé (đồ ăn, nước…) — mở nếu cần tính khách hay sale phụ cho món nào</summary>' +
            '<table><tbody>' + khac.map(hang).join('') + '</tbody></table></details></td></tr>'
          : '') +
        '</tbody></table></div>' +
        '<div style="margin-top:12px"><button class="nut chinh" type="button" id="vkLuu">Lưu bóc tách cho cửa hàng này</button> ' +
        '<span id="vkBao" class="chu-them" style="margin:0"></span></div>';
    }
    h += '</div>';
    var moc = o.querySelector('#dtGhep');
    if (moc) moc.insertAdjacentHTML('beforebegin', h);
    else o.insertAdjacentHTML('beforeend', h);
    noiChonCS(o);
    Array.prototype.forEach.call(o.querySelectorAll('[data-sang-cs]'), function (b) {
      b.addEventListener('click', function () { S.cauHinhCS = b.dataset.sangCs; taiVeKhach(o); taiNhomVe(o); });
    });
    var nut = o.querySelector('#vkLuu');
    if (nut) nut.addEventListener('click', function () {
      var b = {}, bp = {};
      Array.prototype.forEach.call(o.querySelectorAll('#dtVeKhach input[data-vk]'), function (i) { b[i.dataset.vk] = i.value.trim(); });
      Array.prototype.forEach.call(o.querySelectorAll('#dtVeKhach input[data-vp]'), function (i) { bp[i.dataset.vp] = i.value.trim(); });
      nut.disabled = true; nut.textContent = 'Đang lưu…';
      var fd = new FormData(); fd.append('bang', JSON.stringify(b)); fd.append('phu', JSON.stringify(bp)); fd.append('cua_hang', r.cua_hang || cauHinhCS());
      api('ve-khach', { method: 'POST', body: fd }).then(function (r2) {
        var cu = o.querySelector('#dtVeKhach'); if (cu) cu.remove();
        veVeKhach(o, r2);
        var bao = o.querySelector('#vkBao'); if (bao) bao.textContent = 'Đã lưu cho ' + String(r2.cua_hang || '').slice(0, 30) + ' — tab Nhập báo cáo và Đối soát dùng ngay.';
      }).catch(function (e) {
        nut.disabled = false; nut.textContent = 'Lưu bóc tách cho cửa hàng này';
        var bao = o.querySelector('#vkBao'); if (bao) bao.textContent = e.message || e;
      });
    });
  }

  /* ---- SALE VÉ / BÁN LẺ / SALE PHỤ: TÍCH NHÓM MÓN, THEO TỪNG CỬA HÀNG ----
     Anh Thắng 23/09/2026: *"tách giúp anh 2 ô là tiền sale vé và tiền sale bán lẻ"*, *"Tiền Sale
     Phụ"*, *"thêm cấu hình tích trong cấu hình để tính loại nào sale vé, loại nào sale bán lẻ"*.
     Nhóm đã tích = sale vé, còn lại = bán lẻ; cột phụ riêng. Chưa tích gì thì máy tạm theo cột Loại
     món của FABi. */
  function veNhomVe(o, r) {
    var nhom = r.nhom || [], chon = r.nhom_ve || [], chonPhu = r.nhom_phu || {};
    var soPhu = Object.keys(chonPhu).length;
    var cu = o.querySelector('#dtNhomVe'); if (cu) cu.remove();
    var h = '<div class="khung" id="dtNhomVe"><header><h2>Sale vé / Bán lẻ / Sale phụ — theo nhóm món</h2>' +
      '<span class="goi">' + esc(String(r.cua_hang || '').slice(0, 34)) + ' · ' +
      (r.da_cau_hinh
        ? (r.rieng ? 'khai riêng' : '<b style="color:var(--xau)">đang thừa bảng chung</b>') + ' · ' + chon.length + ' nhóm sale vé · ' + soPhu + ' nhóm có phụ'
        : 'chưa cấu hình — đang tạm theo cột Loại món') + '</span></header>' +
      /* 23/09/2026 anh Thắng chỉnh lần cuối: sale phụ là "chiết khấu 20k cho 1 đơn vé combo 80k" —
         số vé × tiền phụ mỗi vé, không phải cộng tiền cả nhóm. */
      '<div class="chu-them" style="margin-top:6px">Ba ô ở tab Nhập báo cáo (và ba cột ở Đối soát) tính theo <b>nhóm món</b> của FABi. ' +
      '<b>Sale vé</b> = các nhóm tích cột "Sale vé" (ví dụ <i>VÉ COMBO.</i> và <i>VÉ LẺ.</i>); <b>Bán lẻ</b> = phần còn lại ' +
      '(<i>ĐÓNG SẴN</i>: đồ ăn, đồ uống); <b>Sale phụ</b> = <b>số vé × tiền phụ mỗi vé</b> gõ ở cột cuối — ví dụ vé combo 80.000đ ' +
      'có 20.000đ phụ (chiết khấu / quà kèm) thì gõ <b>20000</b> ở hàng <i>VÉ COMBO.</i>; nhóm để trống = không có phụ. ' +
      'Nhóm liệt kê từ số liệu 90 ngày gần nhất của cửa hàng đang chọn.</div>' +
      oChonCS('nvCS', r);
    if (!nhom.length) {
      h += '<div class="trong">Cửa hàng này chưa có nhóm món nào trong kho số FABi 90 ngày gần đây.</div>';
    } else {
      h += '<div class="bang-cuon" style="margin-top:8px"><table><thead><tr><th>Sale vé?</th><th>Nhóm món</th><th>Loại món (FABi)</th><th>Đã bán</th><th>Tiền 90 ngày</th><th>Sale phụ mỗi vé (đ)</th></tr></thead><tbody>' +
        nhom.map(function (x) {
          return '<tr><td><input type="checkbox" data-nhom-ve="' + esc(x.nhom) + '"' + (x.ve ? ' checked' : '') + (x.nhom ? '' : ' disabled') + '></td>' +
            '<td style="text-align:left">' + (x.nhom ? esc(x.nhom) : '<i>(không có nhóm)</i>') + '</td>' +
            '<td style="text-align:left;color:var(--ink-3)">' + esc((x.loai || []).join(', ')) + '</td>' +
            '<td class="s">' + nguyen(x.so_luong) + '</td><td class="s">' + tien(x.tien) + '</td>' +
            '<td><input type="number" min="0" step="1000" inputmode="numeric" data-nhom-phu="' + esc(x.nhom) + '" style="width:96px" value="' +
              (x.phu != null ? Math.round(x.phu) : '') + '" placeholder="0"' + (x.nhom ? '' : ' disabled') + '></td></tr>';
        }).join('') + '</tbody></table></div>' +
        '<div style="margin-top:12px"><button class="nut chinh" type="button" id="nvLuu">Lưu cách tách cho cửa hàng này</button> ' +
        '<span id="nvBao" class="chu-them" style="margin:0"></span></div>';
    }
    h += '</div>';
    var moc = o.querySelector('#dtVeKhach') || o.querySelector('#dtGhep');
    if (moc) moc.insertAdjacentHTML('beforebegin', h); else o.insertAdjacentHTML('beforeend', h);
    noiChonCS(o);
    var nut = o.querySelector('#nvLuu');
    if (nut) nut.addEventListener('click', function () {
      var ds = [], dsPhu = {};
      Array.prototype.forEach.call(o.querySelectorAll('input[data-nhom-ve]'), function (c) { if (c.checked) ds.push(c.dataset.nhomVe); });
      /* Sale phụ: [ nhóm => đ/vé ], chỉ gửi ô có số > 0. */
      Array.prototype.forEach.call(o.querySelectorAll('input[data-nhom-phu]'), function (c) {
        var v = parseInt(String(c.value).replace(/[^\d]/g, ''), 10);
        if (v > 0) dsPhu[c.dataset.nhomPhu] = v;
      });
      nut.disabled = true; nut.textContent = 'Đang lưu…';
      var fd = new FormData(); fd.append('nhom_ve', JSON.stringify(ds)); fd.append('nhom_phu', JSON.stringify(dsPhu)); fd.append('cua_hang', r.cua_hang || cauHinhCS());
      api('nhom-ve', { method: 'POST', body: fd }).then(function (r2) {
        veNhomVe(o, r2);
        var b = o.querySelector('#nvBao'); if (b) b.textContent = 'Đã lưu cho ' + String(r2.cua_hang || '').slice(0, 30) + ' — tab Nhập báo cáo và Đối soát tách theo cách mới.';
      }).catch(function (e) {
        nut.disabled = false; nut.textContent = 'Lưu cách tách cho cửa hàng này';
        var b = o.querySelector('#nvBao'); if (b) b.textContent = e.message || e;
      });
    });
  }

  function veQuanTri(o, r) {
    var ds = r.ds || [], ch = r.cua_hang || [];
    var chon = function (ten, gt, ds2, rong) {
      return '<select data-o="' + ten + '">' +
        '<option value="">' + esc(rong) + '</option>' +
        ds2.map(function (x) {
          var v = typeof x === 'string' ? x : x.v, n = typeof x === 'string' ? x : x.n;
          return '<option value="' + esc(v) + '"' + (v === gt ? ' selected' : '') + '>' + esc(n) + '</option>';
        }).join('') + '</select>';
    };
    var quyens = [{ v: 'nhap', n: 'Nhập báo cáo' }, { v: 'duyet', n: 'Nhập và duyệt' }];

    /* ---- NGƯỜI ĐẨY TỪ TRANG NHÂN SỰ (vào bằng PIN) — CẤP VAI Ở ĐÂY ----
       Anh Thắng 23/09/2026: *"chỉ đẩy nhân sự qua, chứ không phân quyền nhiệm vụ trong đó, mà do
       trang tự phân quyền"*. Bên Nhân sự bấm Đẩy là người ấy có mặt ở bảng này với vai TRỐNG —
       vào xem được cơ sở mình, chưa nhập được gì. Chọn vai rồi Lưu là họ nhập được ngay (phiên
       đang mở cũng thấy, vai đọc lại từ bảng mỗi lượt). Đẩy lại bên Nhân sự KHÔNG xoá vai. */
    var pin = r.pin || [];
    var chuaCap = pin.filter(function (x) { return !x.vai; }).length;
    var tuDong = pin.filter(function (x) { return x.tu_dong; });
    var h = '<div class="khung" id="dtNguoiPin"><header><h2>Phân quyền nộp báo cáo — người đẩy từ trang Nhân sự</h2>' +
      '<span class="goi">' + pin.length + ' người' + (chuaCap ? ' · ' + chuaCap + ' chưa cấp' : '') +
      (tuDong.length ? ' · ' + tuDong.length + ' cần kiểm' : '') + '</span></header>';
    if (tuDong.length) {
      /* 23/09/2026: chị Thảo (cửa hàng trưởng) thấy doanh thu cả 15 quán vì bên Chấm công từng tự
         suy vai duyệt cho chị. Những vai ấy 1.58.0 cố ý không đụng — nên phải NÓI RA ở đây, và
         nói rõ vai duyệt nghĩa là xem hết. */
      h += '<div class="canh-ghep"><b>' + tuDong.length + ' người đang mang vai do lối cũ cấp tự động</b> ' +
        '(bên Chấm công suy từ vai chấm công, trước bản 1.58.0) — anh kiểm lại từng người rồi bấm Lưu. ' +
        'Cửa hàng trưởng phải là <b>Nhập báo cáo</b>: vai <b>Nhập và duyệt</b> xem doanh thu <b>mọi</b> ' +
        'cơ sở và nạp được file POS. Đang cần kiểm: ' +
        tuDong.map(function (x) { return esc(x.ho_ten) + (x.vai === 'duyet' ? ' <b style="color:var(--xau)">(duyệt — đang xem mọi cơ sở)</b>' : ''); }).join(', ') +
        '.</div>';
    }
    if (!pin.length) {
      h += '<div class="trong">Chưa có ai được đẩy sang. Vào trang Nhân sự, cột ' +
        '<b>Quản trị báo cáo cơ sở</b>, bấm Đẩy cho cửa hàng trưởng — rồi quay lại đây chọn vai.</div>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr>' +
        '<th>Người</th><th>Cơ sở (từ sổ nhân sự)</th><th>Quyền</th><th></th>' +
        '</tr></thead><tbody>' +
        pin.map(function (x) {
          var cs = (x.coso_ds || []);
          var ten = (x.coso_ten || []);
          return '<tr data-ma-nv="' + esc(x.ma_nv) + '">' +
            '<td>' + esc(x.ho_ten) + '<span style="display:block;color:var(--ink-3);font-size:12px">' +
              esc(x.ma_nv) + (x.co_pin ? '' : ' · <b style="color:var(--xau)">mất PIN (trùng người khác)</b>') +
              (x.tu_dong ? ' · <b style="color:var(--xau)">vai cấp tự động lối cũ — kiểm rồi Lưu</b>' : '') + '</span></td>' +
            '<td style="text-align:left">' + (cs.length ? cs.map(esc).join(', ') : '<span style="color:var(--ink-3)">—</span>') +
              '<span style="display:block;color:var(--ink-3);font-size:12px">' +
              (x.vai === 'duyet'
                ? 'duyệt — xem tổng MỌI cơ sở' + (ten.length ? ' (mã này là quán ' + esc(ten.join(' · ')) + ' — nếu là cửa hàng trưởng thì chọn Nhập báo cáo)' : '')
                : (ten.length ? esc(ten.join(' · ')) : (cs.length ? 'chưa ghép tên POS — khai ở bảng Ghép cơ sở' : ''))) +
              '</span></td>' +
            '<td>' + chon('vai', x.vai || '', quyens, '— chưa cấp (chỉ xem) —') + '</td>' +
            '<td><button class="nut" type="button" data-luu-pin="' + esc(x.ma_nv) + '">Lưu</button></td>' +
          '</tr>';
        }).join('') + '</tbody></table></div>';
    }
    h += '<div class="chu-them"><b>Nhập báo cáo</b>: nhập báo cáo ngày và kho của đúng cơ sở mình. ' +
      '<b>Nhập và duyệt</b>: nhập, xem đối soát mọi cơ sở và nạp được file POS — dành cho kế toán, ' +
      'quản lý. <b>Chưa cấp</b>: đăng nhập được, chỉ xem. Trang Nhân sự chỉ đẩy người sang; vai cấp ở đây.</div></div>';

    h += '<div class="khung"><header><h2>Tài khoản WordPress được nhập báo cáo</h2>' +
      '<span class="goi">' + ds.length + ' người</span></header>';
    if (!ds.length) {
      h += '<div class="trong">Chưa cấp quyền cho tài khoản WordPress nào. Cửa hàng trưởng không cần ' +
        'tài khoản — đẩy từ trang Nhân sự sang rồi cấp vai ở bảng trên. Người văn phòng có tài khoản thì ' +
        'vào Người dùng → sửa tài khoản → mục Doanh thu FABi.</div>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr>' +
        '<th>Người</th><th>Tài khoản</th><th>Cơ sở phụ trách</th><th>Quyền</th><th></th>' +
        '</tr></thead><tbody>' +
        ds.map(function (x) {
          return '<tr data-id="' + x.id + '">' +
            '<td>' + esc(x.ten) + '<span class="sub" style="display:block;color:var(--ink-3);font-size:12px">' +
              esc(x.email || '') + '</span></td>' +
            '<td>' + esc(x.tai_khoan) + '<span style="display:block;color:var(--ink-3);font-size:12px">' +
              esc(x.vai_wp) + '</span></td>' +
            '<td>' + chon('co_so', x.co_so, ch, '— tất cả cơ sở —') + '</td>' +
            '<td>' + chon('quyen', x.quyen, quyens, '— không —') + '</td>' +
            '<td><button class="nut" type="button" data-luu="' + x.id + '">Lưu</button></td>' +
          '</tr>';
        }).join('') + '</tbody></table></div>';
    }
    h += '<div class="chu-them">Gán một cơ sở thì người đó chỉ nhập và chỉ thấy cơ sở mình. ' +
      'Để trống là thấy hết — dành cho anh và kế toán. Người ở đây không nạp được file POS.</div></div>';

    var c = r.cong || {};
    h += '<div class="khung"><header><h2>Cổng nhận từ trang nhân sự</h2></header>' +
      '<div class="chu-them" style="margin-top:6px">Trang nhân sự gọi POST sang địa chỉ dưới đây là ' +
        'nhân viên có quyền nhập ngay, chưa có tài khoản thì tự tạo và trả về mật khẩu.</div>' +
      '<div class="bc-luoi" style="margin-top:12px">' +
        '<label class="bc-o"><b>Địa chỉ</b><input type="text" id="qtUrl" readonly value="' + esc(c.url || '') + '"></label>' +
        '<label class="bc-o"><b>Token</b><input type="text" id="qtToken" readonly value="' + esc(c.token || '') + '"></label>' +
      '</div>' +
      '<div class="bang-cuon"><table><thead><tr><th>Tham số</th><th>Ý nghĩa</th></tr></thead><tbody>' +
        ['token|khoá ở ô trên — bắt buộc',
         'ho_ten|tên hiển thị',
         'email|hoặc tai_khoan — dùng để tìm/tạo tài khoản',
         'tai_khoan|tên đăng nhập',
         'co_so|tên cơ sở, phải trùng tên trong số liệu POS',
         'quyen|nhap (mặc định) hoặc duyet',
         'bo|1 = gỡ quyền người này'].map(function (x) {
          var p = x.split('|');
          return '<tr><td><code>' + esc(p[0]) + '</code></td><td style="text-align:left">' + esc(p[1]) + '</td></tr>';
        }).join('') + '</tbody></table></div></div>';
    o.innerHTML = h;

    Array.prototype.forEach.call(o.querySelectorAll('[data-luu-pin]'), function (b) {
      b.addEventListener('click', function () {
        var tr = b.closest('tr');
        var fd = new FormData();
        fd.append('ma_nv', tr.dataset.maNv);
        fd.append('vai', tr.querySelector('[data-o="vai"]').value);
        b.disabled = true; b.textContent = 'Đang lưu…';
        api('nguoi-vai', { method: 'POST', body: fd }).then(function () {
          b.textContent = 'Đã lưu';
          /* Vẽ lại cả tab: cột Người ở bảng Ghép cơ sở và số "chưa cấp" ở tiêu đề đều đổi theo. */
          setTimeout(taiQuanTri, 600);
        }).catch(function (e) {
          b.disabled = false; b.textContent = 'Lưu'; window.alert(e.message || e);
        });
      });
    });

    Array.prototype.forEach.call(o.querySelectorAll('[data-luu]'), function (b) {
      b.addEventListener('click', function () {
        var tr = b.closest('tr');
        var fd = new FormData();
        fd.append('id', tr.dataset.id);
        fd.append('co_so', tr.querySelector('[data-o="co_so"]').value);
        fd.append('quyen', tr.querySelector('[data-o="quyen"]').value);
        b.disabled = true; b.textContent = 'Đang lưu…';
        api('nhan-su', { method: 'POST', body: fd }).then(function () {
          b.textContent = 'Đã lưu'; setTimeout(function () { b.disabled = false; b.textContent = 'Lưu'; }, 1200);
        }).catch(function (e) {
          b.disabled = false; b.textContent = 'Lưu'; window.alert(e.message || e);
        });
      });
    });
  }

  window.KHHDoanhThu = { mount: mount, tai: tai };

  function tuChay() {
    var g = document.getElementById('khhDt');
    if (g && !g.dataset.xong) { g.dataset.xong = '1'; mount(g); }
  }
  if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', tuChay);
  else tuChay();
})();
