/*
 * wp-ui.js — Phần giao diện chỉ có ở bản WordPress: cổng đăng nhập PIN, hộp Tài khoản
 * (đổi PIN, đăng xuất) và màn quản lý người dùng cho Admin.
 *
 * Bản tĩnh không nạp file này, nên app.js phải chạy được khi không có window.UNCWpUi.
 */
(function (root) {
  'use strict';

  const API = root.UNCApi;
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const VAI = ['Admin', 'Kế toán', 'Xem'];
  let onDangNhap = null;
  let banRon = false;

  /* ---------------------------------------------------------------- cổng PIN */

  function batDau(opts) {
    onDangNhap = (opts && opts.onDangNhap) || null;

    const oPin = $('gatePin');
    const phim = $('keypad');
    if (phim && !phim.children.length) {
      ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', 'OK'].forEach((k) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = k;
        if (k === '⌫' || k === 'OK') b.className = 'aux';
        b.addEventListener('click', () => {
          if (k === 'OK') return vao();
          if (k === '⌫') oPin.value = oPin.value.slice(0, -1);
          else if (oPin.value.length < 8) oPin.value += k;
          oPin.focus();
        });
        phim.appendChild(b);
      });
    }
    oPin.addEventListener('keydown', (e) => { if (e.key === 'Enter') vao(); });
    $('gateBtn').addEventListener('click', vao);

    $('btnTaiKhoan').hidden = false;
    $('btnTaiKhoan').onclick = moTaiKhoan;
    $('tkDong').onclick = () => { $('tkBg').hidden = true; };
    $('tkBg').onclick = (e) => { if (e.target === $('tkBg')) $('tkBg').hidden = true; };
    $('btnTaiLai').hidden = false;
    $('btnTaiLai').onclick = () => { if (onDangNhap) onDangNhap(); };

    API.onAuth((u) => {
      $('btnTaiKhoan').textContent = u ? '👤 ' + u.ten : '👤';
      $('btnTaiKhoan').title = u ? u.ten + ' — ' + u.vai : 'Tài khoản';
    });
  }

  function moCong(loi) {
    $('gate').hidden = false;
    $('gateErr').textContent = loi || '';
    $('gatePin').value = '';
    setTimeout(() => $('gatePin').focus(), 80);
  }
  function dongCong() { $('gate').hidden = true; }

  async function vao() {
    if (banRon) return;
    const pin = ($('gatePin').value || '').trim();
    if (!/^\d{4,8}$/.test(pin)) { $('gateErr').textContent = 'PIN phải 4–8 chữ số'; return; }
    banRon = true;
    $('gateBtn').disabled = true;
    $('gateErr').textContent = '';
    try {
      await API.login(pin);
      dongCong();
      if (onDangNhap) await onDangNhap();
    } catch (e) {
      $('gateErr').textContent = (e && e.message) || 'Không đăng nhập được';
      $('gatePin').value = '';
      $('gatePin').focus();
    } finally {
      banRon = false;
      $('gateBtn').disabled = false;
    }
  }

  /* ---------------------------------------------------------------- hộp tài khoản */

  function moTaiKhoan() {
    const u = API.currentUser();
    if (!u) return moCong();
    const than = $('tkThan');
    than.innerHTML =
      '<dl class="dl"><dt>Tên</dt><dd><b>' + esc(u.ten) + '</b></dd>' +
      '<dt>Vai trò</dt><dd>' + esc(u.vai) + '</dd>' +
      (u.boPhan ? '<dt>Bộ phận</dt><dd>' + esc(u.boPhan) + '</dd>' : '') +
      '</dl>' +
      '<hr><h3>Đổi PIN</h3>' +
      '<label class="field"><span>PIN hiện tại</span><input id="tkPinCu" type="password" inputmode="numeric" autocomplete="current-password"></label>' +
      '<label class="field"><span>PIN mới (4–8 chữ số)</span><input id="tkPinMoi" type="password" inputmode="numeric" autocomplete="new-password"></label>' +
      '<p class="hint" id="tkPinBao"></p>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
      '<button class="btn primary" id="tkDoiPin">Đổi PIN</button>' +
      '<button class="btn danger" id="tkThoat">Đăng xuất</button>' +
      '</div>' +
      (API.laAdmin() ? '<hr><h3>Người dùng</h3><div id="tkNguoiDung"><p class="hint">Đang tải…</p></div>' : '');

    $('tkDoiPin').onclick = doiPin;
    $('tkThoat').onclick = async () => {
      await API.logout();
      $('tkBg').hidden = true;
      location.reload();
    };
    if (API.laAdmin()) veNguoiDung();
    $('tkBg').hidden = false;
  }

  async function doiPin() {
    const cu = ($('tkPinCu').value || '').trim();
    const moi = ($('tkPinMoi').value || '').trim();
    const bao = $('tkPinBao');
    if (!/^\d{4,8}$/.test(moi)) { bao.textContent = 'PIN mới phải 4–8 chữ số.'; return; }
    try {
      await API.call('doiPin', { cu: cu, moi: moi });
      bao.textContent = 'Đã đổi PIN.';
      $('tkPinCu').value = '';
      $('tkPinMoi').value = '';
    } catch (e) {
      bao.textContent = (e && e.message) || 'Không đổi được PIN';
    }
  }

  /* ---------------------------------------------------------------- quản lý người dùng */

  async function veNguoiDung() {
    const hop = $('tkNguoiDung');
    try {
      const r = await API.call('dsNguoiDung', {});
      const ds = r.users || [];
      hop.innerHTML =
        '<div class="tbl-wrap" style="max-height:34vh"><table><thead><tr>' +
        '<th class="nosort">Tên</th><th class="nosort">Vai</th><th class="nosort">Bộ phận</th><th class="nosort"></th>' +
        '</tr></thead><tbody>' +
        ds.map((u) =>
          '<tr><td>' + esc(u.ten) + (u.hoatDong ? '' : ' <span class="tag muted">tắt</span>') + '</td>' +
          '<td>' + esc(u.vai) + '</td><td>' + esc(u.boPhan || '') + '</td>' +
          '<td><button class="btn sm" data-sua="' + u.id + '">Sửa</button></td></tr>'
        ).join('') +
        '</tbody></table></div>' +
        '<button class="btn sm" id="tkThemND" style="margin-top:8px">+ Thêm người dùng</button>';

      hop.querySelectorAll('[data-sua]').forEach((b) => {
        b.onclick = () => formNguoiDung(ds.filter((u) => String(u.id) === b.dataset.sua)[0]);
      });
      $('tkThemND').onclick = () => formNguoiDung(null);
    } catch (e) {
      hop.innerHTML = '<p class="hint" style="color:var(--danger)">' + esc(e && e.message) + '</p>';
    }
  }

  function formNguoiDung(u) {
    const moi = !u;
    u = u || { id: 0, ten: '', vai: 'Kế toán', boPhan: '', hoatDong: true };
    const hop = $('tkNguoiDung');
    hop.innerHTML =
      '<label class="field"><span>Tên</span><input id="ndTen" value="' + esc(u.ten) + '"></label>' +
      '<label class="field"><span>Vai trò</span><select id="ndVai">' +
      VAI.map((v) => '<option' + (v === u.vai ? ' selected' : '') + '>' + esc(v) + '</option>').join('') +
      '</select></label>' +
      '<label class="field"><span>Bộ phận</span><input id="ndBoPhan" value="' + esc(u.boPhan || '') + '"></label>' +
      '<label class="field"><span>PIN ' + (moi ? '(4–8 chữ số)' : '(để trống nếu không đổi)') + '</span>' +
      '<input id="ndPin" type="password" inputmode="numeric" autocomplete="new-password"></label>' +
      '<label class="field chk" style="flex-direction:row;align-items:center;gap:6px">' +
      '<input type="checkbox" id="ndHoatDong"' + (u.hoatDong ? ' checked' : '') + '> Cho đăng nhập</label>' +
      '<p class="hint" id="ndBao"></p>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
      '<button class="btn primary" id="ndLuu">Lưu</button>' +
      '<button class="btn" id="ndHuy">Quay lại</button>' +
      (moi ? '' : '<button class="btn danger" id="ndXoa">Xoá</button>') +
      '</div>';

    $('ndHuy').onclick = veNguoiDung;
    $('ndLuu').onclick = async () => {
      const pin = ($('ndPin').value || '').trim();
      if (moi && !/^\d{4,8}$/.test(pin)) { $('ndBao').textContent = 'Người mới phải có PIN 4–8 chữ số.'; return; }
      if (pin && !/^\d{4,8}$/.test(pin)) { $('ndBao').textContent = 'PIN phải 4–8 chữ số.'; return; }
      try {
        await API.call('luuNguoiDung', {
          user: {
            id: u.id, ten: $('ndTen').value.trim(), vai: $('ndVai').value,
            boPhan: $('ndBoPhan').value.trim(), pin: pin,
            hoatDong: $('ndHoatDong').checked ? 1 : 0,
          },
        });
        veNguoiDung();
      } catch (e) {
        $('ndBao').textContent = (e && e.message) || 'Không lưu được';
      }
    };
    if (!moi) {
      $('ndXoa').onclick = async () => {
        if (!confirm('Xoá người dùng "' + u.ten + '"?')) return;
        try {
          await API.call('xoaNguoiDung', { id: u.id });
          veNguoiDung();
        } catch (e) {
          $('ndBao').textContent = (e && e.message) || 'Không xoá được';
        }
      };
    }
  }

  root.UNCWpUi = { batDau, moCong, dongCong, moTaiKhoan };
})(typeof self !== 'undefined' ? self : this);
