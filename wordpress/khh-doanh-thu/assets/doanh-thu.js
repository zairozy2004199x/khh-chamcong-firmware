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
            tab: 'doanhthu', nhapNgay: '', nhapCH: '' };
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
  function tinhKy() {
    var cf = S.cf || {};
    var cuoi = cf.den_ngay || ymd(new Date());
    var dau = cf.tu_ngay || cuoi;
    var tu, den = cuoi;
    if (S.ky === 'all') tu = dau;
    else if (S.ky === 'thang') tu = cuoi.slice(0, 8) + '01';
    else if (S.ky === 'tay') {
      tu = S.tu || dau; den = S.den || cuoi;
      if (tu > den) { var t = tu; tu = den; den = t; }
    } else {
      var n = parseInt(S.ky, 10);
      tu = doi(cuoi, -(n - 1));
      if (tu < dau) tu = dau;
    }
    S.tu = tu; S.den = den;
    return { tu: tu, den: den };
  }

  function tai() {
    if (S.dangTai) return;
    var k = tinhKy();
    var dai = cach(k.tu, k.den) + 1;
    var truoc = doi(k.tu, -dai);
    S.dangTai = true;
    api('bao-cao?tu=' + truoc + '&den=' + k.den).then(function (r) {
      S.dangTai = false;
      S.ngay = (r && r.ngay) || [];
      ve();
    }).catch(function (e) {
      S.dangTai = false;
      bao(esc(String(e.message || e)), 'loi');
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
  function moHop() {
    if (!nen) dungHop();
    bao_o.hidden = true;
    nen.querySelector('.day').textContent = '';
    nen.setAttribute('data-on', '1');
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
          '</div>' +
          '<div id="dtHdPos">' +
            '<ol><li>Trong CMS FABi: <b>Báo cáo → Báo cáo bán hàng</b> → chọn kỳ → <b>Xuất Excel</b>.</li>' +
            '<li>Thả file xuống ô dưới — file mấy chục MB vẫn được, trang tự cắt nhỏ gửi lên. Máy chủ đọc trang <b>"Tất cả cửa hàng"</b> và bỏ các dòng "Tổng" cộng dồn.</li>' +
            '<li>Nạp lại cùng một ngày thì <b>ghi đè</b> ngày đó, không cộng dồn.</li></ol></div>' +
          '<div id="dtHdSk" hidden>' +
            '<ol><li>Tải sao kê tài khoản nhận tiền nộp của các cơ sở — bản <b>bảng</b> (.xlsx hoặc .csv), không phải PDF.</li>' +
            '<li>Em <b>chỉ lấy tiền vào</b>, bỏ mọi khoản chi. Nạp lại cùng một kỳ không cộng dồn (khoá theo mã giao dịch).</li>' +
            '<li>Nhận mặt cơ sở theo nội dung chuyển khoản hoặc số tài khoản — khai ở <b>Quản trị → Sao kê ngân hàng</b>.</li>' +
            '<li>Tiền nộp sáng hôm sau tính cho doanh thu <b>hôm trước</b> (giờ cắt khai được).</li></ol></div>' +
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
      fd.append('mau', f.slice(i * MAU, (i + 1) * MAU), 'mau.bin');
      trangThai.textContent = (i === tong - 1)
        ? 'Đã gửi xong, máy chủ đang đọc file…'
        : 'Đang gửi ' + f.name + ' — ' + Math.round(i / tong * 100) + '% (' + (i + 1) + '/' + tong + ' mẩu)';
      api('nap-mau', { method: 'POST', body: fd }).then(function (r) {
        i++;
        if (!r.xong && i < tong) { gui(); return; }
        trangThai.textContent = '';
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
          '<button class="tab" type="button" data-tab="doisoat">Đối soát</button>' +
          '<button class="tab" type="button" data-tab="quantri" id="dtTabQT" hidden>Quản trị</button>' +
        '</div>' +
        '<div id="dtTabNhap" hidden></div>' +
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
            '<label for="dtDen">đến</label><input type="date" id="dtDen"></span>' +
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
    q('#dtTu').addEventListener('change', function () { S.ky = 'tay'; S.tu = q('#dtTu').value; tai(); });
    q('#dtDen').addEventListener('change', function () { S.ky = 'tay'; S.den = q('#dtDen').value; tai(); });
    q('#dtChonCH').addEventListener('change', function () { S.ch = q('#dtChonCH').value; ve(); });
    q('#dtXepR').addEventListener('click', function () { S.xepMon = 'r'; ve(); });
    q('#dtXepQ').addEventListener('click', function () { S.xepMon = 'q'; ve(); });
    q('#dtIn').addEventListener('click', function () { window.print(); });
    q('#dtNap').addEventListener('click', moHop);
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
    api('dang-xuat', { method: 'POST' }).catch(function () { /* thẻ hỏng thì thôi, vẫn xoá ở máy */ })
      .then(function () { datThe(''); S.cf = null; khoiDong(); });
  }

  function veNguoiXem(cf) {
    var o = q('#dtAi');
    if (!o) return;
    if (!cf.ten_toi) { o.hidden = true; return; }
    o.hidden = false;
    o.innerHTML = '<span class="ten">' + esc(cf.ten_toi) + '</span>' +
      (cf.bang_pin ? '<button class="vien" type="button" id="dtThoat">Thoát</button>' : '');
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
    q('#dtTabDoiSoat').hidden = t !== 'doisoat';
    q('#dtTabQuanTri').hidden = t !== 'quantri';
    if (t === 'nhap') dungNhap();
    if (t === 'doisoat') taiDoiSoat();
    if (t === 'quantri') taiQuanTri();
  }

  /* ================= tab NHẬP BÁO CÁO NGÀY ================= */
  function homQua() {
    var d = new Date(); d.setDate(d.getDate() - 1); return ymd(d);
  }

  function dungNhap() {
    var o = q('#dtTabNhap');
    if (o.dataset.xong) { napBaoCao(); return; }
    o.dataset.xong = '1';
    var ds = (S.cf && S.cf.cua_hang) || [];
    o.innerHTML =
      '<div class="khung"><header><h2>Nhập báo cáo ngày</h2>' +
        '<span class="goi">Máy POS tự điền phần của nó — cơ sở chỉ nhập phần máy không biết.</span></header>' +
        '<div class="loc" style="margin:14px 0 4px">' +
          '<span class="o"><label for="bcNgay">Ngày</label><input type="date" id="bcNgay"></span>' +
          '<span class="o" id="bcOCH"><label for="bcCH">Cơ sở</label><select id="bcCH">' +
            ds.map(function (c) { return '<option value="' + esc(c) + '">' + esc(c) + '</option>'; }).join('') +
          '</select></span>' +
        '</div>' +
        '<div id="bcPos" class="bc-pos"></div>' +
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
    napBaoCao();
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
              o_pos('Số hoá đơn', nguyen(p.so_hd)) +
              o_pos('Số vé bán', p.so_ve ? nguyen(p.so_ve) : '—') +
              o_pos('Tiền mặt (POS)', tien(p.tien_mat)) +
              o_pos('Chuyển khoản (POS)', tien(p.ck)) +
            '</div>'
          : '<div class="trong">Ngày này chưa có số liệu máy POS trong kho. Nạp file FABi cho ngày đó rồi quay lại.</div>';
        var b = r.bao_cao || {};
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
          : (khoa ? 'chỉ xem' : 'chưa nhập');
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
    var h = '';
    function dong(nhan, v, dv, xau) {
      var lop = v === 0 ? 'khop' : (xau ? 'xau' : 'thuong');
      h += '<div class="bc-lech-o ' + lop + '"><span>' + esc(nhan) + '</span><b>' +
        (v > 0 ? '+' : '') + (dv === 'tien' ? tien(v) : nguyen(v)) + '</b></div>';
    }
    if (dem) dong('Đếm két so với tiền mặt POS', dem - Math.round(p.tien_mat), 'tien', Math.abs(dem - p.tien_mat) > 0);
    if (dem && nop) dong('Đếm được nhưng chưa nộp', dem - nop, 'tien', dem - nop > 0);
    if (khach && ve) dong('Khách vào so với vé bán', khach - ve, 'so', khach - ve > 0);
    o.innerHTML = h || '<div class="bc-lech-o khop"><span>Nhập số vào để hệ thống tính lệch ngay</span><b></b></div>';
  }

  function baoBC(t, loai) {
    var m = q('#bcBao'); m.hidden = false;
    m.className = 'khh-dt-bao' + (loai ? ' ' + loai : ''); m.textContent = t;
  }

  function luuBaoCao(chot) {
    var fd = new FormData();
    fd.append('ngay', q('#bcNgay').value);
    fd.append('cua_hang', q('#bcCH').value);
    ['tien_mat_dem', 'tien_nop', 'so_bill_huy', 'tien_bill_huy', 'tong_chuyen', 'tong_khach', 've_giay']
      .forEach(function (k) { fd.append(k, String(soNhap(k))); });
    fd.append('ghi_chu', q('#bc_ghi_chu').value);
    if (chot) fd.append('chot', '1');
    q('#bcLuu').disabled = true; q('#bcChot').disabled = true;
    api('bao-cao-ngay', { method: 'POST', body: fd }).then(function (r) {
      q('#bcLuu').disabled = false; q('#bcChot').disabled = false;
      baoBC(chot ? 'Đã lưu và chốt ngày.' : 'Đã lưu báo cáo.' +
        (r.sua_lan ? ' (bản trước được giữ lại trong lịch sử sửa)' : ''), 'xong');
      napBaoCao();
    }).catch(function (e) {
      q('#bcLuu').disabled = false; q('#bcChot').disabled = false;
      baoBC(String(e.message || e), 'loi');
    });
  }

  /* ================= tab ĐỐI SOÁT ================= */
  function taiDoiSoat() {
    var o = q('#dtTabDoiSoat');
    var k = tinhKy();
    o.innerHTML = '<div class="khung"><div class="trong">Đang tải…</div></div>';
    api('doi-soat?tu=' + k.tu + '&den=' + k.den + '&cua_hang=' + encodeURIComponent(S.ch))
      .then(function (r) { veDoiSoat(o, r, k); })
      .catch(function (e) {
        o.innerHTML = '<div class="khung"><div class="trong">' + esc(e.message || e) + '</div></div>';
      });
  }

  function veDoiSoat(o, r, k) {
    var ng = r.nguong || { phan_tram: 2, so_tien: 500000 };
    var ds = r.dong || [];
    var chuaNhap = ds.filter(function (x) { return !x.co_bao_cao; }).length;
    var tongLech = 0, tongHuy = 0, soCanh = 0, tongLechNop = 0, soLechNop = 0;
    /* Câu hỏi chính của tab này: TIỀN ĐÃ VỀ TÀI KHOẢN CHƯA. Đếm nó trên MỌI dòng, kể cả dòng cơ
       sở chưa nhập báo cáo — máy POS và ngân hàng đủ trả lời, không phải chờ ai gõ gì. */
    var chuaVe = 0, soChuaVe = 0, soNopMuon = 0;
    ds.forEach(function (x) {
      if (x.qua_han && x.thieu > 0) { chuaVe += x.thieu; soChuaVe++; }
      if (x.nop_muon >= 2) soNopMuon++;
      if (!x.co_bao_cao) return;
      tongLech += x.lech_tm || 0; tongHuy += x.tien_huy || 0;
      if (x.lech_nop) { tongLechNop += x.lech_nop; soLechNop++; }
      if (canhBao(x, ng)) soCanh++;
    });
    var sk = r.sk_chua_gan || { so_dong: 0, so_tien: 0 };
    var h = '<div class="khung"><header><h2>Đối soát cơ sở với máy POS</h2>' +
      '<span class="goi">' + ngayVN(k.tu) + ' → ' + ngayVN(k.den) + ' · ' + ds.length + ' ngày×cơ sở</span></header>' +
      '<div class="the-hang" style="margin:16px 0">' +
        the_nho('Tiền mặt chưa về tài khoản', tien(chuaVe) +
          (soChuaVe ? ' · ' + soChuaVe + ' ngày×cơ sở' : ''), chuaVe > 0 ? 'xau' : '') +
        the_nho('Nộp muộn từ 2 ngày', nguyen(soNopMuon), soNopMuon ? 'xau' : '') +
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
      '<div class="bang-cuon"><table><thead><tr>' +
        '<th>Ngày</th><th>Cơ sở</th><th>POS</th><th>Tiền mặt POS</th>' +
        '<th>Ngân hàng nhận</th><th>Nộp tiền</th>' +
        '<th>Đếm két</th><th>Lệch</th><th>Bill huỷ</th><th>Khách − vé</th><th>Người nhập</th>' +
      '</tr></thead><tbody>';
    ds.forEach(function (x) {
      var do_ = canhBao(x, ng);
      h += '<tr' + (do_ ? ' class="canh"' : '') + '>' +
        '<td>' + esc(ngayVN(x.ngay)) + '</td>' +
        '<td>' + esc(String(x.cua_hang).slice(0, 34)) + '</td>' +
        '<td class="s">' + tien(x.doanh_thu) + '</td>' +
        '<td class="s">' + tien(x.pos_tm) + '</td>' +
        /* Hai cột tiền nộp luôn hiện — không nấp sau việc cơ sở đã nhập báo cáo hay chưa. */
        '<td class="s">' + (x.co_bank
          ? tien(x.nop_bank) +
            (x.nop_lan > 1 ? '<span class="nho"> ' + x.nop_lan + ' lần</span>' : '') +
            (x.lech_nop
              ? '<span class="nho xau" title="Cơ sở khai đã nộp ' + esc(tien(x.nop)) + '"> khai ' +
                (x.lech_nop > 0 ? '+' : '') + tienGon(x.lech_nop) + '</span>'
              : '')
          : '<span class="khai">—</span>') + '</td>' +
        '<td>' + theNop(x, r.co_bank) + '</td>' +
        (x.co_bao_cao
          ? '<td class="s">' + tien(x.dem) + '</td>' +
            '<td class="s">' + (x.lech_tm ? (x.lech_tm > 0 ? '+' : '') + tien(x.lech_tm) : '0') + '</td>' +
            '<td class="s">' + (x.bill_huy ? nguyen(x.bill_huy) + ' · ' + tien(x.tien_huy) : '—') + '</td>' +
            '<td class="s">' + (x.khach && x.so_ve ? nguyen(x.khach - Math.round(x.so_ve)) : '—') + '</td>' +
            '<td>' + esc(x.nguoi || '') + (x.chot ? ' ✓' : '') + '</td>'
          : '<td colspan="5" class="chua">chưa nhập báo cáo ngày</td>') +
        '</tr>';
    });
    h += '</tbody></table></div>' +
      '<div class="chu-them">Bôi đỏ khi lệch tiền mặt hoặc phần chưa nộp vượt ' +
        phan(ng.phan_tram) + ' doanh thu ngày, hoặc vượt ' + tien(ng.so_tien) + '. ' +
        (r.co_bank
          ? 'Cột <b>Ngân hàng nhận</b> là tiền thật sự về tài khoản theo sao kê. Cột <b>Nộp tiền</b> so ' +
            'nó với số phải nộp (đếm két nếu cơ sở đã đếm, không thì tiền mặt máy POS) — nên trả lời ' +
            'được cả những ngày cơ sở chưa nhập báo cáo. Tiền hôm nay chỉ tính là thiếu sau giờ cắt ' +
            'của ngày hôm sau.'
          : 'Chưa nạp sao kê nên chưa biết tiền đã về tài khoản hay chưa.') +
        '</div></div>';
    o.innerHTML = h;
  }

  /**
   * Một ô trả lời đúng câu "nộp tiền chưa".
   *
   * Bốn trạng thái, và chúng KHÁC NHAU thật sự:
   *   · chưa tới hạn  — tiền hôm nay, sáng mai mới mang ra ngân hàng. Không gọi tên ai.
   *   · đủ            — về đủ (cho lệch dưới 1.000 ₫ vì lẻ tiền mặt).
   *   · thiếu / chưa  — đã quá hạn mà tiền chưa về, hoặc về không đủ.
   *   · muộn          — có về, nhưng mấy ngày sau. Tiền không mất, nhưng nằm trong tay người ta.
   */
  function theNop(x, coBank) {
    if (!coBank) return '<span class="chip cho">chưa nạp sao kê</span>';
    if (x.phai_nop <= 0) return '<span class="chip">không có tiền mặt</span>';
    var thieu = x.thieu || 0;
    if (Math.abs(thieu) < 1000) {
      return '<span class="chip du">đã nộp đủ</span>' +
        (x.nop_muon >= 2 ? '<span class="chip muon">muộn ' + x.nop_muon + ' ngày</span>' : '');
    }
    if (thieu > 0) {
      if (!x.qua_han) return '<span class="chip cho">chưa tới hạn nộp</span>';
      return '<span class="chip thieu">' + (x.co_bank ? 'thiếu ' + tienGon(thieu) : 'chưa nộp ' + tienGon(thieu)) + '</span>';
    }
    return '<span class="chip du">nộp dư ' + tienGon(-thieu) + '</span>';
  }

  function canhBao(x, ng) {
    /* Tiền quá hạn mà chưa về là cảnh báo ĐỘC LẬP với việc cơ sở đã nhập báo cáo hay chưa —
       không nhập báo cáo không làm khoản tiền ấy hết thiếu. */
    var m = (x.qua_han && x.thieu > 0) ? x.thieu : 0;
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
    }).catch(function (e) {
      o.innerHTML = '<div class="khung"><div class="trong">' + esc(e.message || e) + '</div></div>';
    });
  }

  /* ---- SAO KÊ NGÂN HÀNG: giờ cắt + bảng nhận mặt cơ sở ----
     Cột "Thực nộp" lấy ở đây chứ không lấy số cơ sở tự khai: ai giữ tiền mà cũng tự khai mình
     nộp bao nhiêu thì con số ấy không kiểm được gì. */
  function veSaoKe(o, r) {
    var ch = r.cua_hang || [], ghep = r.ghep || [], chua = r.chua_gan || [], theo = r.theo_ch || [];
    var h = '<div class="khung" id="dtSaoKe"><header><h2>Sao kê ngân hàng</h2>' +
      '<span class="goi">' + (r.so_dong
        ? nguyen(r.so_dong) + ' khoản · ' + tien(r.tong) + ' · ' + ngayVN(r.tu_ngay) + ' → ' + ngayVN(r.den_ngay)
        : 'chưa nạp sao kê') + '</span></header>';

    /* Cổng SePay đã có sẵn trong plugin Ghế Massage — kéo thẳng từ đó, khỏi tải file hàng tháng. */
    if (r.co_cong_ghe) {
      h += '<div class="canh-ghep" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">' +
        '<span style="flex:1 1 320px">Site này đã có <b>cổng SePay</b> (trong plugin Ghế Massage) — ' +
        'giao dịch ngân hàng về thẳng máy chủ, không phải tải file. ' +
        (r.tu_keo ? 'Đang <b>tự kéo mỗi giờ</b>.' : 'Chưa bật tự kéo.') + '</span>' +
        '<button class="nut chinh" type="button" id="dtKeoSk">Kéo giao dịch về</button>' +
        (r.tu_keo ? '' : '<label class="o"><input type="checkbox" id="dtTuKeo" checked> tự kéo mỗi giờ</label>') +
        '</div>';
    }

    if (!r.so_dong) {
      h += '<div class="trong">Chưa có giao dịch nào. ' + (r.co_cong_ghe
        ? 'Bấm <b>Kéo giao dịch về</b> ở trên.'
        : 'Bấm <b>Nạp báo cáo</b> ở trên, chọn thẻ <b>Sao kê ngân hàng</b> rồi thả file .xlsx/.csv ' +
          'tải từ ngân hàng xuống.') + '</div>';
    } else if (chua.length) {
      h += '<div class="canh-ghep">Còn <b>' + nguyen(chua.length) + ' khoản</b> chưa nhận ra cơ sở. ' +
        'Xem nội dung chuyển khoản bên dưới, lấy một mẩu chữ đặc trưng (tên quán, mã quán, hoặc ' +
        'số tài khoản nhận) rồi khai vào bảng nhận mặt.</div>' +
        '<div class="bang-cuon" style="margin-top:10px"><table><thead><tr>' +
          '<th>Ngày</th><th>Số tiền</th><th style="text-align:left">Nội dung chuyển khoản</th><th>Tài khoản</th>' +
        '</tr></thead><tbody>' +
        chua.slice(0, 40).map(function (x) {
          return '<tr><td>' + esc(ngayVN(x.ngay)) + '</td><td class="s">' + tien(x.so_tien) + '</td>' +
            '<td style="text-align:left"><code class="nd">' + esc(x.noi_dung || '—') + '</code></td>' +
            '<td>' + esc(x.tai_khoan || '—') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    } else {
      h += '<div class="bang-cuon"><table><thead><tr><th style="text-align:left">Cơ sở</th>' +
        '<th>Số khoản</th><th>Tổng nhận được</th></tr></thead><tbody>' +
        theo.map(function (x) {
          return '<tr><td style="text-align:left">' + esc(x.cua_hang) + '</td>' +
            '<td class="s">' + nguyen(x.n) + '</td><td class="s">' + tien(x.t) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }

    /* Bảng nhận mặt + giờ cắt */
    h += '<h3 class="tieu-nho">Nhận mặt cơ sở</h3>' +
      '<div class="chu-them">Mỗi dòng: một mẩu chữ có trong nội dung chuyển khoản hoặc số tài khoản ' +
      'nhận → cơ sở tương ứng. Không phân biệt hoa thường và dấu. Khoá dài được xét trước khoá ' +
      'ngắn, nên "TUTU TAN PHU" không bị "TUTU TAN" nuốt mất.</div>' +
      '<div class="bang-cuon" style="margin-top:10px"><table id="dtBankBang"><thead><tr>' +
        '<th style="text-align:left">Chữ trong nội dung / số tài khoản</th><th style="text-align:left">Cơ sở</th><th></th>' +
      '</tr></thead><tbody>' +
      (ghep.length ? ghep.map(function (g) { return dongBank(g.khoa, g.cua_hang, ch); }).join('')
        : dongBank('', '', ch)) +
      '</tbody></table></div>' +
      '<div style="margin-top:12px">' +
        '<button class="nut" type="button" id="dtBankThem">Thêm dòng</button> ' +
        '<span class="o" style="margin-left:14px"><label for="dtGioCat">Giờ cắt</label>' +
          '<input type="number" id="dtGioCat" min="0" max="23" step="1" style="width:66px" value="' +
          esc(String(r.gio_cat)) + '"></span> ' +
        '<span class="chu-them">Tiền vào trước giờ này tính cho doanh thu <b>hôm trước</b> — ' +
          'quán đóng cửa đêm, sáng hôm sau mới mang tiền ra ngân hàng.</span>' +
      '</div>' +
      '<div style="margin-top:12px"><button class="nut chinh" type="button" id="dtLuuBank">Lưu và gán lại</button> ' +
        '<span id="dtBankBao" class="chu-them"></span></div></div>';

    o.insertAdjacentHTML('beforeend', h);

    var nutKeo = o.querySelector('#dtKeoSk');
    if (nutKeo) {
      nutKeo.addEventListener('click', function () {
        nutKeo.disabled = true; nutKeo.textContent = 'Đang kéo…';
        var fd = new FormData();
        var tk = o.querySelector('#dtTuKeo');
        if (tk && tk.checked) fd.append('tu_dong', '1');
        api('sao-ke-keo', { method: 'POST', body: fd }).then(function (kq) {
          var v = kq.vua_keo || {};
          window.alert('Đã mang về ' + nguyen(v.keo || 0) + ' khoản tiền vào.' +
            (v.bo_ghe ? '\nBỏ ' + nguyen(v.bo_ghe) + ' khoản là tiền khách trả ghế — đó là doanh thu, ' +
              'không phải nhân viên nộp tiền.' : '') +
            ((v.chua_gan && v.chua_gan.so_dong)
              ? '\nCòn ' + nguyen(v.chua_gan.so_dong) + ' khoản chưa nhận ra cơ sở — khai bảng nhận mặt bên dưới.'
              : ''));
          taiQuanTri();
        }).catch(function (e) {
          nutKeo.disabled = false; nutKeo.textContent = 'Kéo giao dịch về';
          window.alert(e.message || e);
        });
      });
    }

    var bang = o.querySelector('#dtBankBang tbody');
    o.querySelector('#dtBankThem').addEventListener('click', function () {
      bang.insertAdjacentHTML('beforeend', dongBank('', '', ch));
      noiXoaBank(bang);
    });
    noiXoaBank(bang);

    o.querySelector('#dtLuuBank').addEventListener('click', function () {
      var ds = [];
      Array.prototype.forEach.call(bang.querySelectorAll('tr'), function (tr) {
        var k = tr.querySelector('[data-khoa]').value.trim();
        var c = tr.querySelector('[data-ch]').value;
        if (k && c) ds.push({ khoa: k, cua_hang: c });
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

  function dongBank(khoa, cua, ch) {
    return '<tr><td style="text-align:left"><input type="text" data-khoa value="' + esc(khoa) +
      '" placeholder="VD: TUTU TAN PHU hoặc 0123456789" style="width:100%"></td>' +
      '<td style="text-align:left"><select data-ch><option value="">— chọn cơ sở —</option>' +
      ch.map(function (t) {
        return '<option value="' + esc(t) + '"' + (t === cua ? ' selected' : '') + '>' + esc(t) + '</option>';
      }).join('') + '</select></td>' +
      '<td><button class="nut" type="button" data-xoa>Xoá</button></td></tr>';
  }

  function noiXoaBank(bang) {
    Array.prototype.forEach.call(bang.querySelectorAll('[data-xoa]'), function (b) {
      if (b.dataset.noi) return;
      b.dataset.noi = '1';
      b.addEventListener('click', function () { b.closest('tr').remove(); });
    });
  }

  /* ---- ghép MÃ cơ sở (bên nhân sự) ↔ TÊN cơ sở (bên máy POS) ----
     Hai hệ gọi cùng một cái quán bằng hai cái tên: nhân sự ghi "FZ_SC_VIVO_T4", máy POS ghi
     "FUNZONE - Vivo City (...)". Không ai đoán hộ được, nên khai một lần ở đây. */
  function veGhep(o, r) {
    var ghep = r.ghep || [], ch = r.cua_hang || [], ng = r.nguoi || [], thieu = r.chua_ghep || [];
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
                return '<label><input type="checkbox" data-ghep="' + esc(g.ma) + '" value="' + esc(t) + '"' +
                  (chon.indexOf(t) >= 0 ? ' checked' : '') + '><span>' + esc(t) + '</span></label>';
              }).join('') +
            '</div></td>' +
            '<td style="text-align:left">' + (nguoi.length
              ? nguoi.map(function (x) {
                  return esc(x.ho_ten) + ' <span style="color:var(--ink-3)">(' + esc(x.ma_nv) +
                    (x.vai === 'duyet' ? ', duyệt — xem tổng mọi cơ sở'
                      : ((x.coso_ds || []).length > 1 ? ', ' + x.coso_ds.length + ' cơ sở' : '')) + ')</span>';
                }).join('<br>')
              : '<span style="color:var(--ink-3)">—</span>') + '</td></tr>';
        }).join('') + '</tbody></table></div>' +
        '<div style="margin-top:12px"><button class="nut chinh" type="button" id="dtLuuGhep">Lưu bảng ghép</button> ' +
        '<span id="dtGhepBao" class="chu-them"></span></div>';
    }
    h += '</div>';
    o.insertAdjacentHTML('afterbegin', h);

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

    var h = '<div class="khung"><header><h2>Ai được nhập báo cáo cơ sở</h2>' +
      '<span class="goi">' + ds.length + ' người</span></header>';
    if (!ds.length) {
      h += '<div class="trong">Chưa cấp quyền cho ai. Đẩy cửa hàng trưởng từ trang nhân sự sang, ' +
        'hoặc vào Người dùng → sửa tài khoản → mục Doanh thu FABi.</div>';
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
