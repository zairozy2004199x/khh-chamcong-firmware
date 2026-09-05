/*
 * Giao diện: nhận file, hiện sheet nhận diện được, chạy tổng hợp, tải kết quả.
 * Phần nặng nằm hết trong Web Worker (js/worker.js) nên trang không bao giờ đứng.
 */
'use strict';

(function () {
  var STORAGE_KEY = 'vatrec.danhMucThem.v1';
  var SETTINGS_KEY = 'vatrec.caiDat.v1';

  var worker = null;
  var files = [];          // [{ name, size, sheets, trangThai }]
  var pending = 0;         // số file đang đọc dở
  var ketQua = null;       // payload 'done' gần nhất
  var dangChay = false;    // đang có một lượt tổng hợp chạy dở
  var canChayLai = false;  // có file mới đọc xong trong lúc đang chạy
  var nguonDaBiet = [];    // các file đã có trang đối soát ở lượt chạy trước
  var blobUrl = null;

  var el = {};
  ['coSo', 'kyTu', 'kyDen', 'ngayHoaDon', 'rate', 'phapNhan', 'noiDung', 'tenKhach',
    'kieuXuat', 'today', 'dayTable', 'thNgay', 'drop', 'fileInput', 'fileList', 'stepSheets', 'sheetTable', 'extraTable',
    'addExtra', 'clearExtra', 'extraCount', 'run', 'download', 'status', 'error',
    'results', 'cards', 'streamTable', 'warnTable', 'warnBadge', 'unmappedPanel',
    'unmappedTable', 'addAllUnmapped', 'pointTable', 'fileTable',
    'resultTabs', 'paneThietLap', 'paneCauHinh', 'paneTongHop', 'paneKenh',
    'locTieuDe', 'locGhiChu',
    'locTable', 'locTai',
    'locNgay', 'locNhom', 'locTrangThai', 'locKhoi', 'locKhoiWrap', 'locTim',
    'locAnRong', 'locXoaLoc', 'locTong', 'locCotSo', 'locThongKe'
  ].forEach(function (id) {
    el[id] = document.getElementById(id);
  });

  /* ------------------------------------------------------------- tiện ích */

  var THU = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

  /** Ngày ISO -> "dd/mm" cho bảng trên trang. */
  function viDate(iso) {
    if (!iso) return '';
    var parts = iso.split('-');
    return parts[2] + '/' + parts[1];
  }

  function thuTrongTuan(iso) {
    return THU[new Date(iso + 'T00:00:00Z').getUTCDay()];
  }

  function money(value) {
    return (value || 0).toLocaleString('vi-VN');
  }

  function text(tag, content, className) {
    var node = document.createElement(tag);
    if (content !== undefined && content !== null) node.textContent = String(content);
    if (className) node.className = className;
    return node;
  }

  function setStatus(message, kind) {
    el.status.textContent = message || '';
    el.status.className = 'status' + (kind ? ' ' + kind : '');
  }

  function showError(message) {
    el.error.textContent = message;
    el.error.hidden = !message;
  }

  /* -------------------------------------------------------- kỳ mặc định */

  function defaultPeriod() {
    var saved = null;
    try { saved = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null'); } catch (err) { saved = null; }
    if (saved) {
      ['coSo', 'kyTu', 'kyDen', 'ngayHoaDon', 'phapNhan', 'noiDung', 'tenKhach', 'kieuXuat'].forEach(function (key) {
        if (saved[key] !== undefined && el[key]) el[key].value = saved[key];
      });
      if (saved.rate) el.rate.value = saved.rate;
      if (el.kyTu.value) return;
    }
    // Mặc định là tháng trước — kỳ hay đối soát nhất.
    var now = new Date();
    var first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    var last = new Date(now.getFullYear(), now.getMonth(), 0);
    el.kyTu.value = iso(first);
    el.kyDen.value = iso(last);
    el.ngayHoaDon.value = iso(last);
  }

  function iso(date) {
    return date.getFullYear() + '-' +
      String(date.getMonth() + 1).padStart(2, '0') + '-' +
      String(date.getDate()).padStart(2, '0');
  }

  function saveSettings() {
    try {
      localStorage.setItem(SETTINGS_KEY, JSON.stringify({
        coSo: el.coSo.value, kyTu: el.kyTu.value, kyDen: el.kyDen.value,
        ngayHoaDon: el.ngayHoaDon.value, rate: el.rate.value, phapNhan: el.phapNhan.value,
        noiDung: el.noiDung.value, tenKhach: el.tenKhach.value, kieuXuat: el.kieuXuat.value
      }));
    } catch (err) { /* trình duyệt chặn localStorage thì bỏ qua, không ảnh hưởng tính toán */ }
  }

  /* ---------------------------------------------------------------- worker */

  /**
   * Chuỗi truy vấn phiên bản, đọc lại từ thẻ <script> đã nạp chính file này.
   * Nhờ vậy số phiên bản chỉ khai một lần trong index.html, và mọi file con đều
   * được tải mới khi số đó đổi — nếu không thì trình duyệt vẫn dùng bản cũ trong
   * bộ đệm sau khi cập nhật, và người dùng thấy công cụ như chưa hề được sửa.
   */
  var VERSION_QUERY = (function () {
    var script = document.currentScript;
    if (!script) {
      var all = document.getElementsByTagName('script');
      script = all[all.length - 1];
    }
    var src = (script && script.getAttribute('src')) || '';
    var at = src.indexOf('?');
    return at >= 0 ? src.slice(at) : '';
  }());

  /**
   * Địa chỉ file worker. Bản thường dùng đường dẫn tương đối kèm số phiên bản;
   * bản đóng thành một trang đơn (không có file rời) đặt sẵn biến ghi đè này
   * thành một blob URL.
   */
  function workerUrl() {
    return (typeof window.VATREC_WORKER_URL === 'string' && window.VATREC_WORKER_URL)
      ? window.VATREC_WORKER_URL
      : 'js/worker.js' + VERSION_QUERY;
  }

  function ensureWorker() {
    if (worker) return worker;
    try {
      worker = new Worker(workerUrl());
    } catch (err) {
      showError('Trình duyệt không tạo được luồng xử lý nền: ' + err.message);
      throw err;
    }
    worker.onmessage = function (event) { onWorkerMessage(event.data || {}); };
    worker.onerror = function (event) {
      showError('Lỗi trong luồng xử lý: ' + (event.message || 'không rõ nguyên nhân'));
      setStatus('');
      pending = 0;
      dangChay = false;
      canChayLai = false;
      refreshRunButton();
    };
    return worker;
  }

  function onWorkerMessage(message) {
    if (message.type === 'progress') {
      setStatus(message.phase + (message.name ? ' (' + message.name + ')' : ''), 'busy');
      if (message.name) markFile(message.name, message.phase, 'busy');
    } else if (message.type === 'scanned') {
      onScanned(message);
    } else if (message.type === 'done') {
      onDone(message);
    } else if (message.type === 'error') {
      showError(message.message + (message.stack ? '\n\n' + message.stack : ''));
      setStatus('');
      pending = 0;
      dangChay = false;
      canChayLai = false;
      refreshRunButton();
    }
  }

  /* ----------------------------------------------------------- nhận file */

  function addFiles(list) {
    showError('');
    Array.prototype.forEach.call(list, function (file) {
      if (files.some(function (item) { return item.name === file.name; })) return;
      files.push({ name: file.name, size: file.size, sheets: [], trangThai: 'Đang chờ...' });
      pending += 1;
      renderFiles();
      file.arrayBuffer().then(function (buffer) {
        ensureWorker().postMessage({ type: 'scan', name: file.name, buffer: buffer }, [buffer]);
      }).catch(function (err) {
        pending -= 1;
        markFile(file.name, 'Không đọc được: ' + err.message, 'bad');
        refreshRunButton();
      });
    });
    refreshRunButton();
  }

  function markFile(name, trangThai, kind) {
    var item = files.find(function (f) { return f.name === name; });
    if (!item) return;
    item.trangThai = trangThai;
    item.kind = kind;
    renderFiles();
  }

  function onScanned(message) {
    var item = files.find(function (f) { return f.name === message.name; });
    if (item) {
      item.sheets = message.sheets;
      item.kind = message.sheets.length ? 'ok' : 'bad';
      item.trangThai = message.sheets.length
        ? message.sheets.length + ' sheet dùng được'
        : 'Không có sheet nào nhận diện được';
    }
    pending -= 1;
    renderFiles();
    renderSheets();
    refreshRunButton();
    if (pending > 0) return;
    setStatus('Đã đọc xong ' + files.length + ' file.', 'ok');
    // Thả file nào là tách sẵn tab của file đó luôn, khỏi phải bấm chạy rồi mới
    // thấy gì. Thả cả bộ thì vẫn chỉ chạy một lần, sau khi đọc xong file cuối.
    tuDongChay();
  }

  /**
   * Chạy tổng hợp ngay sau khi đọc xong file, nếu không có gì cản.
   *
   * Không chạy khi đang có lượt chạy dở, khi chưa chọn kỳ, hay khi không sheet
   * nào được chọn — mấy trường hợp đó để người dùng bấm nút và đọc lỗi cho rõ.
   */
  function tuDongChay() {
    // Thả thêm file trong lúc đang chạy thì ghi nhận lại, chạy tiếp khi xong —
    // nếu không, kết quả sẽ thiếu đúng cái file vừa thả.
    if (dangChay) { canChayLai = true; return; }
    if (el.run.disabled) return;
    if (!el.kyTu.value || !el.kyDen.value || el.kyTu.value > el.kyDen.value) return;
    run();
  }

  function renderFiles() {
    renderTabs();
    el.fileList.textContent = '';
    files.forEach(function (item) {
      var row = text('div', null, 'file-row' + (item.kind === 'busy' ? ' busy' : '') +
        (item.kind === 'bad' ? ' bad' : ''));
      row.appendChild(text('span', '📄'));
      row.appendChild(text('span', item.name, 'name'));
      var mb = (item.size / 1048576).toFixed(1);
      row.appendChild(text('span', mb + ' MB · ' + item.trangThai, 'meta'));
      el.fileList.appendChild(row);
    });
  }

  /* -------------------------------------------------- bảng sheet nhận diện */

  var CATALOG_KINDS = {
    catalog_store: 1, catalog_vnpay: 1, catalog_payoo: 1, catalog_momo: 1, catalog_diem: 1
  };

  function renderSheets() {
    var body = el.sheetTable.tBodies[0];
    // Giữ lại lựa chọn của người dùng khi vẽ lại bảng vì có file mới.
    // Dòng danh mục không có ô "tên luồng" nên phải kiểm tra trước khi đọc.
    var truoc = {};
    Array.prototype.forEach.call(body.rows, function (row) {
      var luongInput = row.querySelector('input[type=text]');
      truoc[row.dataset.file + '|' + row.dataset.sheet] = {
        dung: row.querySelector('input[type=checkbox]').checked,
        luong: luongInput ? luongInput.value : ''
      };
    });

    body.textContent = '';
    var any = false;
    files.forEach(function (file) {
      file.sheets.forEach(function (sheet) {
        any = true;
        var key = file.name + '|' + sheet.name;
        var cu = truoc[key];
        var isCatalog = CATALOG_KINDS[sheet.kind];

        var row = document.createElement('tr');
        row.dataset.file = file.name;
        row.dataset.sheet = sheet.name;
        row.dataset.kind = sheet.kind;

        var tickCell = text('td', null, 'tick');
        var tick = document.createElement('input');
        tick.type = 'checkbox';
        tick.checked = cu ? cu.dung : true;
        tick.addEventListener('change', refreshRunButton);
        tickCell.appendChild(tick);
        row.appendChild(tickCell);

        row.appendChild(text('td', file.name));
        row.appendChild(text('td', sheet.name));

        var kindCell = document.createElement('td');
        kindCell.appendChild(text('span', sheet.label, 'pill ' + (isCatalog ? 'cat' : 'src')));
        row.appendChild(kindCell);

        row.appendChild(text('td', isCatalog ? '—' : money(sheet.soDong), 'num-col'));

        var luongCell = document.createElement('td');
        if (isCatalog) {
          luongCell.appendChild(text('span', '—', 'hint'));
        } else {
          var input = document.createElement('input');
          input.type = 'text';
          input.value = (cu && cu.luong) ? cu.luong : goiYLuong(sheet);
          luongCell.appendChild(input);
        }
        row.appendChild(luongCell);
        body.appendChild(row);
      });
    });
    el.stepSheets.hidden = !any;
  }

  /** Tên luồng gợi ý: sheet QR thì lấy tên sheet, còn lại lấy tên cổng. */
  function goiYLuong(sheet) {
    if (sheet.kind === 'payoo') return 'Payoo';
    if (sheet.kind === 'vnpay') return 'VNPay';
    if (sheet.kind === 'zalo') return 'Zalo mini app';
    if (sheet.kind === 'momo') return 'MoMo';
    return sheet.name.trim();
  }

  function chonHienTai() {
    return Array.prototype.map.call(el.sheetTable.tBodies[0].rows, function (row) {
      var luongInput = row.querySelector('input[type=text]');
      return {
        file: row.dataset.file,
        sheet: row.dataset.sheet,
        dung: row.querySelector('input[type=checkbox]').checked,
        luong: luongInput ? luongInput.value.trim() : ''
      };
    });
  }

  function refreshRunButton() {
    var coNguon = chonHienTai().some(function (item) {
      var row = timRow(item);
      return item.dung && row && !CATALOG_KINDS[row.dataset.kind];
    });
    el.run.disabled = pending > 0 || !coNguon;
  }

  function timRow(item) {
    return Array.prototype.find.call(el.sheetTable.tBodies[0].rows, function (row) {
      return row.dataset.file === item.file && row.dataset.sheet === item.sheet;
    });
  }

  /* ------------------------------------------------------ danh mục bổ sung */

  var EXTRA_FIELDS = ['channel', 'code', 'tenDiem', 'maMisa', 'khuVuc', 'dichVu', 'phapNhan'];
  var CHANNELS = [['qr', 'QR VietQR'], ['payoo', 'Payoo'], ['vnpay', 'VNPay'],
    ['zalo', 'Zalo'], ['momo', 'MoMo']];

  function loadExtra() {
    var saved = [];
    try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (err) { saved = []; }
    if (!Array.isArray(saved)) saved = [];
    saved.forEach(addExtraRow);
    updateExtraCount();
  }

  function saveExtra() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(extraRows())); } catch (err) { /* bỏ qua */ }
    updateExtraCount();
  }

  function extraRows() {
    return Array.prototype.map.call(el.extraTable.tBodies[0].rows, function (row) {
      var item = {};
      EXTRA_FIELDS.forEach(function (field) {
        var input = row.querySelector('[data-field="' + field + '"]');
        item[field] = input ? input.value.trim() : '';
      });
      return item;
    }).filter(function (item) { return item.code || item.tenDiem; });
  }

  function addExtraRow(values) {
    values = values || {};
    var row = document.createElement('tr');

    var channelCell = document.createElement('td');
    var select = document.createElement('select');
    select.dataset.field = 'channel';
    CHANNELS.forEach(function (pair) {
      var option = document.createElement('option');
      option.value = pair[0];
      option.textContent = pair[1];
      if (values.channel === pair[0]) option.selected = true;
      select.appendChild(option);
    });
    select.addEventListener('change', function () { saveExtra(); khaiXongThiChayLai(); });
    channelCell.appendChild(select);
    row.appendChild(channelCell);

    ['code', 'tenDiem', 'maMisa', 'khuVuc', 'dichVu', 'phapNhan'].forEach(function (field) {
      var cell = document.createElement('td');
      var input = document.createElement('input');
      input.type = 'text';
      input.dataset.field = field;
      input.value = values[field] || '';
      input.addEventListener('change', function () { saveExtra(); khaiXongThiChayLai(); });
      cell.appendChild(input);
      row.appendChild(cell);
    });

    var removeCell = text('td', null, 'tick');
    var remove = text('button', '✕', 'btn ghost mini');
    remove.type = 'button';
    remove.title = 'Xoá dòng';
    remove.addEventListener('click', function () {
      row.remove();
      saveExtra();
      khaiXongThiChayLai();
    });
    removeCell.appendChild(remove);
    row.appendChild(removeCell);

    el.extraTable.tBodies[0].appendChild(row);
    return row;
  }

  function updateExtraCount() {
    var n = extraRows().length;
    el.extraCount.textContent = n ? n + ' dòng đã khai (lưu trong trình duyệt)' : '';
  }

  /* ---------------------------------------------------------------- chạy */

  function run() {
    showError('');
    if (dangChay) return;
    if (!el.kyTu.value || !el.kyDen.value) {
      showError('Chưa chọn kỳ báo cáo.');
      return;
    }
    if (el.kyTu.value > el.kyDen.value) {
      showError('“Từ ngày” phải trước “Đến ngày”.');
      return;
    }
    saveSettings();
    dangChay = true;
    el.run.disabled = true;
    el.download.hidden = true;
    setStatus('Đang tổng hợp...', 'busy');

    ensureWorker().postMessage({
      type: 'run',
      chon: chonHienTai(),
      danhMucThem: extraRows(),
      coSo: el.coSo.value.trim() || 'Cơ sở',
      kyTu: el.kyTu.value,
      kyDen: el.kyDen.value,
      ngayHoaDon: el.ngayHoaDon.value || el.kyDen.value,
      rate: parseFloat(el.rate.value),
      phapNhan: el.phapNhan.value.trim(),
      noiDung: el.noiDung.value.trim(),
      tenKhach: el.tenKhach.value.trim(),
      theoNgay: el.kieuXuat.value === 'ngay'
    });
  }

  function onDone(message) {
    ketQua = message;
    dangChay = false;
    el.run.disabled = false;
    // Có file thả thêm trong lúc chạy: bỏ kết quả vừa xong, chạy lại cho đủ.
    if (canChayLai) { canChayLai = false; tuDongChay(); return; }
    setStatus('Xong — ' + money(message.soGiaoDich) + ' giao dịch, ' +
      money(message.soDiem) + ' điểm xuất hoá đơn.', 'ok');

    if (blobUrl) URL.revokeObjectURL(blobUrl);
    var blob = new Blob([message.file], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });
    blobUrl = URL.createObjectURL(blob);
    el.download.hidden = false;

    // Có file mới xuất hiện thì mở luôn trang đối soát của nó — thả lẻ từng
    // file là để xem ngay file đó. Chạy lại trên đúng bộ file cũ thì đứng yên,
    // khỏi cướp mất chỗ đang xem.
    var moi = (message.loc || []).filter(function (item) {
      return nguonDaBiet.indexOf(item.nguon) < 0;
    });
    nguonDaBiet = (message.loc || []).map(function (item) { return item.nguon; });
    renderResults(message);
    if (moi.length) chonTab(moi[moi.length - 1].nguon);
  }

  /** Tải file .xlsx của riêng đối soát đang xem. */
  function downloadMot() {
    var kenh = timKenh(tabDangXem);
    if (!kenh || !kenh.file) return;
    var blob = new Blob([kenh.file], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });
    var url = URL.createObjectURL(blob);
    taiVe(url, 'doi-soat-' + tenTepAnToan(kenh.nguon) + '.xlsx');
    // Thu hồi sau khi trình duyệt kịp bắt đầu tải; giữ mãi là rò bộ nhớ.
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  }

  /** Tên file tải về: bỏ đuôi cũ và các ký tự hệ điều hành không nhận. */
  function tenTepAnToan(nguon) {
    return String(nguon).replace(/\.[^.]+$/, '').replace(/[\\/:*?"<>|]+/g, '-').slice(0, 80);
  }

  function taiVe(url, ten) {
    var link = document.createElement('a');
    link.href = url;
    link.download = ten;
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function download() {
    if (!blobUrl) return;
    var kyTen = el.kyTu.value === el.kyDen.value ? el.kyTu.value : el.kyTu.value + '_' + el.kyDen.value;
    taiVe(blobUrl, 'VAT_' + (el.coSo.value.trim() || 'coso') + '_' + kyTen +
      (el.kieuXuat.value === 'ngay' ? '_theo-ngay' : '') + '.xlsx');
  }

  /* ------------------------------------------------------------- kết quả */

  /* ------------------------------------------------------- tab kết quả */

  /*
   * Mỗi đối soát một trang riêng: kiểm từng file rồi mới tin số tổng, thay vì
   * trộn hết vào một trang rồi không biết sai từ đâu. Trang "Thiết lập" để khai
   * kỳ và thả file, "Tổng hợp" là kết quả gộp cuối cùng, các trang còn lại là
   * một file đầu vào.
   */
  var THIET_LAP = '\u0000thiet-lap';
  var CAU_HINH = '\u0000cau-hinh';
  var TONG_HOP = '\u0000tong-hop';

  var locData = [];              // [{ nguon, rows, ngay, coPhi, luong, thongKe }]
  var tabDangXem = THIET_LAP;

  function renderTabs(message) {
    locData = (message && message.loc) || locData;
    el.results.hidden = tabDangXem === THIET_LAP || tabDangXem === CAU_HINH;
    el.resultTabs.textContent = '';

    themTab(THIET_LAP, 'Thiết lập', files.length ? files.length + ' file' : '');

    // Số mã còn chờ gán hiện ngay trên nhãn tab, để không phải mở ra mới biết.
    var chuaGan = locData.reduce(function (a, item) { return a + (item.soChuaMap || 0); }, 0);
    themTab(CAU_HINH, 'Cấu hình', chuaGan ? chuaGan + ' mã chờ gán' : 'đã gán đủ',
      chuaGan > 0);

    locData.forEach(function (item, i) {
      themTab(item.nguon, (i + 1) + '. ' + tenNganGon(item.nguon), item.rows.length + ' mã');
    });
    if (locData.length) themTab(TONG_HOP, 'Tổng hợp', '');

    // Trang đang xem có thể vừa biến mất (chạy lại với bộ file khác).
    var con = Array.prototype.some.call(el.resultTabs.children, function (button) {
      return button.dataset.tab === tabDangXem;
    });
    chonTab(con ? tabDangXem : THIET_LAP);
  }

  /** Tên file rút gọn cho nhãn tab; tên đầy đủ vẫn hiện trong tiêu đề bảng. */
  function tenNganGon(nguon) {
    var ten = String(nguon).replace(/\.[^.]+$/, '');
    return ten.length > 28 ? ten.slice(0, 27) + '…' : ten;
  }

  function themTab(id, nhan, phu, canhBao) {
    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('role', 'tab');
    button.dataset.tab = id;
    button.appendChild(document.createTextNode(nhan));
    if (phu) button.appendChild(text('span', phu, 'count' + (canhBao ? ' warn' : '')));
    button.addEventListener('click', function () { chonTab(id); });
    el.resultTabs.appendChild(button);
  }

  function chonTab(id) {
    tabDangXem = id;
    Array.prototype.forEach.call(el.resultTabs.children, function (button) {
      button.setAttribute('aria-selected', button.dataset.tab === id ? 'true' : 'false');
    });
    el.paneThietLap.hidden = id !== THIET_LAP;
    el.paneCauHinh.hidden = id !== CAU_HINH;
    el.paneTongHop.hidden = id !== TONG_HOP;
    el.paneKenh.hidden = id === THIET_LAP || id === CAU_HINH || id === TONG_HOP;
    el.results.hidden = id === THIET_LAP || id === CAU_HINH;
    if (!el.paneKenh.hidden) renderLoc(timKenh(id));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function timKenh(nguon) {
    return locData.filter(function (item) { return item.nguon === nguon; })[0] || null;
  }

  /* ------------------------------------------------------ bảng lọc file */

  /*
   * Bảng của một file: mã điểm bán × nhóm, chọn một ngày là ra thẳng số của ngày
   * đó. Chọn "Cả kỳ" thì cộng gộp toàn kỳ.
   */
  function renderLoc(kenh) {
    if (!kenh) return;
    el.locTieuDe.textContent = 'Đối soát — ' + kenh.nguon;
    el.locTai.hidden = !kenh.file;
    el.locGhiChu.textContent = 'Chỉ tính riêng file này, gom theo mã điểm bán × nhóm × ngày. ' +
      'Mở ra là thấy sẵn ngày mới nhất có dữ liệu, muốn xem ngày khác thì chọn lại ở ô Ngày. ' +
      'Bảng dựng thẳng từ dữ liệu gốc nên hiện cả mã chưa có trong danh mục — ' +
      'so thẳng được với file gốc mà không phải bóc tách gì thêm.';
    // Chỉ Payoo có cột phí trong sao kê, các nguồn khác không có gì để đổi khối.
    el.locKhoiWrap.hidden = !kenh.coPhi;
    if (!kenh.coPhi) el.locKhoi.value = 'tien';
    renderLocThongKe(kenh);

    // Chỉ liệt kê ngày thật sự có phát sinh, khỏi phải cuộn qua ngày trống.
    // Ngày mới nhất lên đầu và được chọn sẵn: dữ liệu về theo ngày nên mở ra là
    // thấy ngay ngày vừa cập nhật, muốn xem ngày khác thì chọn lại.
    var coPhatSinh = kenh.ngay.filter(function (ngay) {
      return kenh.rows.some(function (row) { return row.tien[ngay]; });
    }).reverse();
    var dangChon = el.locNgay.value;
    el.locNgay.textContent = '';
    coPhatSinh.forEach(function (ngay) {
      var option = document.createElement('option');
      option.value = ngay;
      option.textContent = viDate(ngay) + ' · ' + thuTrongTuan(ngay);
      el.locNgay.appendChild(option);
    });
    var caKy = document.createElement('option');
    caKy.value = '';
    caKy.textContent = '— Cả kỳ —';
    el.locNgay.appendChild(caKy);
    el.locNgay.value = coPhatSinh.indexOf(dangChon) >= 0 ? dangChon : (coPhatSinh[0] || '');

    // Ô Nhóm dựng theo đúng các nhóm có trong file này, không phải danh sách cứng.
    var nhom = [];
    kenh.rows.forEach(function (row) {
      if (nhom.indexOf(row.nhom) < 0) nhom.push(row.nhom);
    });
    var nhomCu = el.locNhom.value;
    el.locNhom.textContent = '';
    var tatCa = document.createElement('option');
    tatCa.value = '';
    tatCa.textContent = nhom.length > 1 ? '— Tất cả (' + nhom.length + ') —' : '— Tất cả —';
    el.locNhom.appendChild(tatCa);
    nhom.forEach(function (ten) {
      var option = document.createElement('option');
      option.value = ten;
      option.textContent = ten;
      el.locNhom.appendChild(option);
    });
    el.locNhom.value = nhom.indexOf(nhomCu) >= 0 ? nhomCu : '';

    fillLoc();
  }

  /** Bỏ hết ô lọc, trừ ô Ngày — ngày là trục chính của bảng, không phải bộ lọc phụ. */
  function xoaLoc() {
    el.locNhom.value = '';
    el.locTrangThai.value = '';
    el.locTim.value = '';
    el.locAnRong.checked = false;
    fillLoc();
  }

  /*
   * Dải số của riêng file đang xem, đặt ngay trên bảng: luồng đọc được, số tiền
   * vào hoá đơn, và các phần bị tách riêng. Nhìn một chỗ là biết file đó đã đọc
   * đủ chưa, khỏi phải mở sheet Đối soát ra dò.
   *
   * Chỉ hiện mục nào khác 0 — file sạch thì dải này ngắn, file có vấn đề thì
   * đúng chỗ có vấn đề nổi lên.
   */
  function renderLocThongKe(kenh) {
    var tk = kenh.thongKe;
    el.locThongKe.textContent = '';
    var muc = [
      ['Luồng tiền', kenh.luong.join(', ') || '—', false, true],
      ['Số GD', money(tk.soGiaoDich), false, true],
      ['Vào hoá đơn', money(tk.soTien) + ' đ', false, true],
      ['Chưa có danh mục', money(tk.chuaMapSoTien) + ' đ', true, !!tk.chuaMapSoTien],
      ['Vãng lai', money(tk.vangLai) + ' đ', true, !!tk.vangLai],
      ['Ngoài kỳ', money(tk.ngoaiKy) + ' đ', true, !!tk.ngoaiKy],
      ['Trùng mã', money(tk.trungLap) + ' đ', true, !!tk.trungLap],
      ['Pháp nhân khác', money(tk.loaiKhacPhapNhan) + ' đ', true, !!tk.loaiKhacPhapNhan],
      ['Không đọc được ngày', money(tk.khongCoNgay), true, !!tk.khongCoNgay]
    ];
    muc.forEach(function (item) {
      if (!item[3]) return;
      var chip = text('span', null, 'chip' + (item[2] ? ' warn' : ''));
      chip.appendChild(text('span', item[0], 'k'));
      chip.appendChild(text('span', item[1], 'v'));
      el.locThongKe.appendChild(chip);
    });
    canhBaoKy(kenh);
  }

  /*
   * Kỳ báo cáo không trùm dữ liệu trong file là thủ phạm hay gặp nhất của việc
   * "chạy xong mà mọi số đều bằng 0": giao dịch bị loại hết vì ngoài kỳ. Nói
   * thẳng ra và cho một nút đặt kỳ đúng theo file, thay vì để người dùng ngồi
   * đoán xem mình khai sai chỗ nào.
   */
  function canhBaoKy(kenh) {
    var tk = kenh.thongKe;
    var ngay = kenh.ngayDuLieu;
    if (!ngay || !tk.ngoaiKy) return;
    var trumHet = tk.soTien === 0;

    var canh = text('div', null, 'ky-canh-bao' + (trumHet ? ' nang' : ''));
    canh.appendChild(text('strong',
      trumHet ? 'Toàn bộ giao dịch của file này nằm ngoài kỳ báo cáo.'
              : 'Một phần giao dịch của file này nằm ngoài kỳ báo cáo.'));
    canh.appendChild(text('span',
      ' Dữ liệu trong file chạy từ ' + viDate(ngay.tu) + '/' + ngay.tu.slice(0, 4) +
      ' đến ' + viDate(ngay.den) + '/' + ngay.den.slice(0, 4) +
      ', còn kỳ đang đặt là ' + viDate(el.kyTu.value) + '/' + el.kyTu.value.slice(0, 4) +
      ' – ' + viDate(el.kyDen.value) + '/' + el.kyDen.value.slice(0, 4) + '.'));

    var nut = text('button', 'Đặt kỳ đúng theo file này', 'btn ghost mini');
    nut.type = 'button';
    nut.addEventListener('click', function () {
      el.kyTu.value = ngay.tu;
      el.kyDen.value = ngay.den;
      if (!el.ngayHoaDon.value || el.ngayHoaDon.value < ngay.tu || el.ngayHoaDon.value > ngay.den) {
        el.ngayHoaDon.value = ngay.den;
      }
      saveSettings();
      tuDongChay();
    });
    canh.appendChild(nut);
    el.locThongKe.appendChild(canh);
  }

  function fillLoc() {
    var kenh = timKenh(tabDangXem);
    if (!kenh) return;
    var ngay = el.locNgay.value;
    var khoi = el.locKhoi.value;
    var nhan = { tien: 'Số xuất hoá đơn', phi: 'Phí cổng thu', ve: 'Cổng phải trả về TK' };
    el.locCotSo.textContent = nhan[khoi];

    var loc = {
      nhom: el.locNhom.value,
      trangThai: el.locTrangThai.value,
      tim: keyText(el.locTim.value),
      anRong: el.locAnRong.checked
    };

    var tong = 0;
    var giu = [];
    kenh.rows.forEach(function (row) {
      var soTien = giaTriLoc(row, ngay, khoi);
      if (!hopLoc(row, soTien, loc)) return;
      tong += soTien;
      giu.push({ row: row, soTien: soTien });
    });

    // Đánh STT theo điểm chứ không theo dòng: một điểm có thể có nhiều nhóm.
    // Đánh sau khi lọc, để số thứ tự luôn liền mạch trên phần đang xem.
    var stt = 0;
    var truoc = null;
    var rows = giu.map(function (item) {
      var khoaDiem = item.row.tenDiem || item.row.code;
      var so = '';
      if (khoaDiem !== truoc) { stt += 1; truoc = khoaDiem; so = stt; }
      return {
        cells: [
          { value: so, num: true },
          item.row.tenDiem || '(chưa có trong danh mục)',
          item.row.maMisa || '—',
          item.row.code,
          item.row.nhom,
          { value: item.soTien ? money(item.soTien) : '—', num: true }
        ]
      };
    });

    if (!rows.length) {
      rows.push({ cells: [{ value: '' }, 'Không có dòng nào khớp bộ lọc.', '', '', '', ''] });
    } else {
      rows.push({
        cells: [{ value: '', num: true }, { value: 'TỔNG', bold: true }, '', '', '',
          { value: money(tong), num: true, bold: true }]
      });
    }
    fillTable(el.locTable, rows);

    var phan = [(ngay ? 'Ngày ' + viDate(ngay) : 'Cả kỳ') + ': ' + money(tong) + ' đ'];
    if (giu.length !== kenh.rows.length) {
      phan.push(giu.length + '/' + kenh.rows.length + ' dòng');
    }
    phan.push(stt + ' điểm');
    el.locTong.textContent = phan.join('  ·  ');
  }

  /** Một dòng có qua được hết các ô lọc đang đặt hay không. */
  function hopLoc(row, soTien, loc) {
    if (loc.nhom && row.nhom !== loc.nhom) return false;
    if (loc.trangThai === 'co' && !row.tenDiem) return false;
    if (loc.trangThai === 'khong' && row.tenDiem) return false;
    if (loc.anRong && !soTien) return false;
    if (loc.tim) {
      var trong = keyText(row.tenDiem) + ' ' + keyText(row.code) + ' ' +
        keyText(row.maMisa) + ' ' + keyText(row.nhom);
      if (trong.indexOf(loc.tim) < 0) return false;
    }
    return true;
  }

  /** Khoá so khớp cho ô Tìm: bỏ dấu, hạ chữ thường — gõ "hue" ra được "Huế". */
  function keyText(value) {
    return String(value === null || value === undefined ? '' : value)
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd').replace(/Đ/g, 'D')
      .toLowerCase().trim();
  }

  function giaTriLoc(row, ngay, khoi) {
    function lay(bang) { return ngay ? (bang[ngay] || 0) : cong(bang); }
    if (khoi === 'phi') return lay(row.phi);
    if (khoi === 've') return lay(row.tien) - lay(row.phi);
    return lay(row.tien);
  }

  function cong(bang) {
    return Object.keys(bang).reduce(function (a, k) { return a + bang[k]; }, 0);
  }

  function renderResults(message) {
    el.cards.textContent = '';
    // Đếm số việc cần người xem lại, khớp với các dòng được tô ở bảng Cảnh báo.
    var canhBao = message.chuaMap.length + (message.vangLai ? 1 : 0) +
      (message.soChuaGanDiem ? 1 : 0) + (message.khongCoNgay ? 1 : 0) +
      (message.trungLapSoGiaoDich ? 1 : 0);
    [
      ['Tổng doanh thu (có VAT)', money(message.tong) + ' đ', false],
      ['Chưa VAT', money(message.chuaVat) + ' đ', false],
      ['Tiền thuế VAT', money(message.vat) + ' đ', false],
      ['Số hoá đơn', money(message.soDiem), false],
      ['Giao dịch đã tính', money(message.soGiaoDich), false],
      ['Cần xem lại', money(canhBao), canhBao > 0]
    ].forEach(function (item) {
      var card = text('div', null, 'card' + (item[2] ? ' warn' : ''));
      card.appendChild(text('div', item[0], 'k'));
      card.appendChild(text('div', item[1], 'v'));
      el.cards.appendChild(card);
    });

    fillTable(el.streamTable, message.tongTheoLuong.map(function (item) {
      return [item.luong, { value: money(item.soTien), num: true }];
    }).concat([[{ value: 'TỔNG CỘNG', bold: true }, { value: money(message.tong), num: true, bold: true }]]));

    var warnings = [
      ['Mã điểm bán chưa có trong danh mục', message.chuaMap.length,
        message.chuaMap.reduce(function (a, i) { return a + i.soTien; }, 0)],
      ['Giao dịch vãng lai (không mã điểm bán)', message.vangLaiSoGiaoDich, message.vangLai],
      ['Mã cửa hàng chưa gán điểm xuất hoá đơn', message.soChuaGanDiem, null],
      ['Giao dịch ngoài kỳ (đã loại)', null, message.ngoaiKy],
      ['Điểm thuộc pháp nhân khác (đã loại)', null, message.loaiKhacPhapNhan],
      ['Không đọc được ngày', message.khongCoNgay, null],
      ['Giao dịch trùng mã (đã bỏ bản thứ hai)', message.trungLapSoGiaoDich, message.trungLap]
    ];
    fillTable(el.warnTable, warnings.map(function (item) {
      var co = (item[1] || 0) > 0 || (item[2] || 0) > 0;
      return {
        warn: co,
        cells: [item[0],
          { value: item[1] === null ? '—' : money(item[1]), num: true },
          { value: item[2] === null ? '—' : money(item[2]), num: true }]
      };
    }));
    var soCanhBao = warnings.filter(function (i) { return (i[1] || 0) > 0 || (i[2] || 0) > 0; }).length;
    el.warnBadge.hidden = !soCanhBao;
    el.warnBadge.textContent = soCanhBao;

    fillTable(el.fileTable, (message.theoFile || []).map(function (item, i) {
      var canhBao = item.chuaMapSoTien || item.vangLai || item.trungLap;
      return {
        warn: !!canhBao,
        cells: [
          { value: i + 1, num: true },
          item.nguon,
          item.luong.join(', ') || '—',
          { value: money(item.soGiaoDich), num: true },
          { value: money(item.soDiem), num: true },
          { value: money(item.soTien), num: true },
          { value: item.chuaMapSoTien ? money(item.chuaMapSoTien) : '—', num: true },
          { value: item.vangLai ? money(item.vangLai) : '—', num: true },
          { value: item.ngoaiKy ? money(item.ngoaiKy) : '—', num: true },
          { value: item.trungLap ? money(item.trungLap) : '—', num: true }
        ]
      };
    }));

    renderTabs(message);
    renderUnmapped(macChuaGan());

    el.thNgay.hidden = !message.theoNgay;
    fillTable(el.pointTable, message.diem.map(function (point, i) {
      var cells = [{ value: i + 1, num: true }];
      if (message.theoNgay) cells.push(viDate(point.ngay));
      return cells.concat([point.tenDiem, point.maMisa, point.khuVuc,
        { value: money(point.chuaVat), num: true },
        { value: money(point.vat), num: true },
        { value: money(point.coVat), num: true }]);
    }));

    fillTable(el.dayTable, (message.theoNgayBang || []).map(function (item) {
      return {
        warn: item.tong === 0,
        cells: [viDate(item.ngay), thuTrongTuan(item.ngay), { value: money(item.tong), num: true }]
      };
    }));
  }

  /*
   * Gom mã chưa gán từ dữ liệu thô của mọi file, không lấy từ kết quả đã tổng
   * hợp: giao dịch bị loại vì ngoài kỳ không vào kết quả, nhưng mã của nó vẫn
   * cần gán — nếu không, sửa lại kỳ xong vẫn kẹt đúng chỗ cũ.
   */
  function macChuaGan() {
    var theoMa = Object.create(null);
    var thuTu = [];
    locData.forEach(function (kenh) {
      kenh.rows.forEach(function (row) {
        if (row.tenDiem) return;
        var key = row.channel + '\u001f' + row.code;
        if (!theoMa[key]) {
          theoMa[key] = {
            channel: row.channel, code: row.code, soTien: 0, soGiaoDich: null,
            goiY: row.goiY || []
          };
          thuTu.push(theoMa[key]);
        }
        theoMa[key].soTien += row.tongTien;
      });
    });
    return thuTu.sort(function (a, b) { return b.soTien - a.soTien; });
  }

  function renderUnmapped(list) {
    el.unmappedPanel.hidden = !list.length;
    var body = el.unmappedTable.tBodies[0];
    body.textContent = '';
    list.forEach(function (item) {
      var row = document.createElement('tr');
      row.className = 'warn';
      row.appendChild(text('td', item.channel));
      row.appendChild(text('td', item.code));
      // Bảng này gom từ dữ liệu thô nên chỉ có số tiền, không đếm giao dịch.
      row.appendChild(text('td', item.soGiaoDich === null ? '—' : money(item.soGiaoDich), 'num-col'));
      row.appendChild(text('td', money(item.soTien), 'num-col'));

      // Ô đề xuất là một danh sách chọn, không phải chữ chết: máy chỉ đoán, người
      // khai vẫn phải xác nhận hoặc chọn lại trước khi tiền được gán vào điểm.
      var goiY = item.goiY || [];
      var chonCell = document.createElement('td');
      var chon = document.createElement('select');
      goiY.forEach(function (g, i) {
        var option = document.createElement('option');
        option.value = String(i);
        option.textContent = g.tenDiem + ' — ' + mucChac(g.diem);
        option.title = g.lyDo;
        chon.appendChild(option);
      });
      var trong = document.createElement('option');
      trong.value = '';
      trong.textContent = goiY.length ? '— tự nhập —' : '— không có đề xuất, tự nhập —';
      chon.appendChild(trong);
      chonCell.appendChild(chon);
      row.appendChild(chonCell);

      var actionCell = document.createElement('td');
      var button = text('button', 'Khai vào danh mục', 'btn ghost mini');
      button.type = 'button';
      button.addEventListener('click', function () {
        khaiVaoDanhMuc(item, chon.value === '' ? null : goiY[+chon.value]);
        button.disabled = true;
        button.textContent = 'Đã thêm ↓';
        khaiXongThiChayLai();
      });
      actionCell.appendChild(button);
      row.appendChild(actionCell);
      body.appendChild(row);
    });
  }

  /** Đổi điểm khớp 0..1 thành chữ, vì con số lẻ không nói lên điều gì cho người đọc. */
  function mucChac(diem) {
    if (diem >= 0.85) return 'gần chắc chắn';
    if (diem >= 0.6) return 'nhiều khả năng';
    return 'có thể';
  }

  function khaiVaoDanhMuc(item, goiY) {
    var daCo = extraRows().some(function (row) {
      return row.channel === item.channel && row.code === item.code;
    });
    if (daCo) { saveExtra(); return; }
    var values = { channel: item.channel, code: item.code };
    if (goiY) {
      // Chép luôn khu vực / dịch vụ / pháp nhân của điểm được chọn, để dòng mới
      // đầy đủ như các điểm đã có sẵn chứ không chỉ có mỗi cái tên.
      ['tenDiem', 'maMisa', 'khuVuc', 'dichVu', 'phapNhan'].forEach(function (field) {
        values[field] = goiY[field] || '';
      });
    }
    var row = addExtraRow(values);
    saveExtra();
    // Không có đề xuất nào thì tên điểm còn trống — đưa con trỏ vào đúng ô đó.
    if (row && !values.tenDiem) {
      var o = row.querySelector('input[data-field="tenDiem"]');
      if (o) o.focus();
    }
  }

  /** Khai xong thì tính lại ngay, khỏi phải nhớ bấm nút. */
  function khaiXongThiChayLai() {
    updateExtraCount();
    tuDongChay();
  }

  /**
   * Đổ dữ liệu vào bảng. Mỗi dòng là mảng ô, hoặc { warn, cells } khi cần tô màu.
   * Ô là chuỗi, hoặc { value, num, bold }.
   */
  function fillTable(table, rows) {
    var body = table.tBodies[0];
    body.textContent = '';
    rows.forEach(function (row) {
      var cells = Array.isArray(row) ? row : row.cells;
      var tr = document.createElement('tr');
      if (!Array.isArray(row) && row.warn) tr.className = 'warn';
      cells.forEach(function (cell) {
        var spec = (cell && typeof cell === 'object') ? cell : { value: cell };
        var td = text('td', spec.value === undefined ? '' : spec.value, spec.num ? 'num-col' : null);
        if (spec.bold) td.style.fontWeight = '700';
        tr.appendChild(td);
      });
      body.appendChild(tr);
    });
  }

  /* ---------------------------------------------------------------- gắn sự kiện */

  el.drop.addEventListener('click', function () { el.fileInput.click(); });
  el.drop.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); el.fileInput.click(); }
  });
  el.fileInput.addEventListener('change', function () {
    addFiles(el.fileInput.files);
    el.fileInput.value = '';
  });

  ['dragenter', 'dragover'].forEach(function (name) {
    el.drop.addEventListener(name, function (event) {
      event.preventDefault();
      el.drop.classList.add('over');
    });
  });
  ['dragleave', 'drop'].forEach(function (name) {
    el.drop.addEventListener(name, function (event) {
      event.preventDefault();
      el.drop.classList.remove('over');
    });
  });
  el.drop.addEventListener('drop', function (event) {
    if (event.dataTransfer && event.dataTransfer.files) addFiles(event.dataTransfer.files);
  });
  // Thả nhầm ra ngoài khung thì trình duyệt sẽ mở file đó thay vì xử lý — chặn lại.
  window.addEventListener('dragover', function (event) { event.preventDefault(); });
  window.addEventListener('drop', function (event) { event.preventDefault(); });

  el.addExtra.addEventListener('click', function () { addExtraRow(); updateExtraCount(); });
  el.clearExtra.addEventListener('click', function () {
    if (!extraRows().length || window.confirm('Xoá toàn bộ danh mục đã khai?')) {
      el.extraTable.tBodies[0].textContent = '';
      saveExtra();
    }
  });
  ['locNgay', 'locNhom', 'locTrangThai', 'locKhoi', 'locAnRong']
    .forEach(function (id) { el[id].addEventListener('change', fillLoc); });
  el.locTim.addEventListener('input', fillLoc);
  el.locXoaLoc.addEventListener('click', xoaLoc);

  el.addAllUnmapped.addEventListener('click', function () {
    if (!ketQua) return;
    // Khai hàng loạt thì lấy đề xuất tốt nhất của từng mã; dòng nào máy không
    // đoán được thì vẫn thêm với tên điểm để trống cho người khai tự điền.
    macChuaGan().forEach(function (item) {
      khaiVaoDanhMuc(item, (item.goiY && item.goiY[0]) || null);
    });
    khaiXongThiChayLai();
    document.getElementById('stepExtra').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Sao kê về theo ngày, nên nhịp chạy thường gặp là đối soát dữ liệu của hôm qua.
  el.today.addEventListener('click', function () {
    var homQua = iso(new Date(Date.now() - 86400000));
    el.kyTu.value = homQua;
    el.kyDen.value = homQua;
    el.ngayHoaDon.value = homQua;
    saveSettings();
  });

  el.run.addEventListener('click', run);
  el.download.addEventListener('click', download);
  el.locTai.addEventListener('click', downloadMot);
  ['coSo', 'kyTu', 'kyDen', 'ngayHoaDon', 'rate', 'phapNhan', 'noiDung', 'tenKhach', 'kieuXuat']
    .forEach(function (id) { el[id].addEventListener('change', saveSettings); });

  defaultPeriod();
  loadExtra();
  refreshRunButton();
  renderTabs();
}());
