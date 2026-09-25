/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  09_Router   ← MỤC LỤC DỰ ÁN, ĐỌC FILE NÀY TRƯỚC
 * ---------------------------------------------------------------------------
 *  00_Config            Hằng số · quy tắc tính tiền · schema các tab
 *  01_Core              Lớp truy cập sheet DUY NHẤT (đọc/ghi/khoá/audit)
 *  02_Auth              Đăng nhập · phiên · phân quyền 3 vai trò
 *  03_CauHinh           NV · cơ sở · cụm máy · ô máy · danh mục hàng
 *  04_TinhToan          ★ TOÀN BỘ công thức tiền và cảnh báo nằm ở đây
 *  05_BaoCao            Vòng đời báo cáo nhân viên · auto-carry đầu kỳ
 *  06_Duyet             Duyệt song song 2 kế toán · reset chữ ký thông minh
 *  07_Anh               Upload ảnh Drive (nén ở client, gửi từng ảnh)
 *  08_LienThongKeToan   Đối soát QR/TM/CK · số liệu cho web kế toán
 *  09_Router            doGet · include · hàm chạy tay lần đầu   ← FILE NÀY
 *
 * ── Web NHÂN VIÊN (mặc định, hoặc ?app=thutien) ──
 *  Index · Css · Js01_Core · Js02_BaoCao · Js03_Anh
 *
 * ── Web KẾ TOÁN (?app=ketoan) ──
 *  KT_Index · KT_Css · KtJs01_Core · KtJs02_Duyet · KtJs03_DoiSoat · KtJs04_CauHinh
 *
 * SỬA Ở ĐÂU:
 *  · Sai số tiền            → 04_TinhToan
 *  · Sai luồng duyệt        → 06_Duyet  (giao diện: KtJs02_Duyet)
 *  · Sai đối soát nộp tiền  → 08_LienThongKeToan (giao diện: KtJs03_DoiSoat)
 *  · Sai đọc/ghi sheet      → 01_Core
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① ĐIỂM VÀO WEB ────────────────────*/

/**
 * Một deployment phục vụ cả 2 web:
 *   .../exec                → web nhân viên
 *   .../exec?app=ketoan     → web kế toán
 */
function doGet(e) {
  var app = ((e && e.parameter && e.parameter.app) || 'thutien').toLowerCase();
  var isKT = (app === 'ketoan' || app === 'kt');

  var t = HtmlService.createTemplateFromFile(isKT ? 'KT_Index' : 'Index');
  t.appName = isKT ? 'JP Capsule — Web kế toán' : 'JP Capsule — Web nhân viên';

  return t.evaluate()
    .setTitle(isKT ? 'JP Capsule — Kế toán' : 'JP Capsule — Nhân viên')
    .addMetaTag('viewport', 'width=device-width, initial-scale=1')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function include(name) {
  return HtmlService.createHtmlOutputFromFile(name).getContent();
}

/*──────────────────── ② CHẠY TAY LẦN ĐẦU ────────────────────*/

/**
 * ĐẶT CẤU HÌNH — chạy tay khi cần ĐỔI sheet, hoặc khi có nhiều sheet cùng tên.
 *
 * Bình thường KHÔNG cần gọi: `jpSheetId_()` tự dò và tự ghi property ở lần chạy
 * đầu. Hàm này để đổi sang sheet khác, hoặc xử lý trường hợp dò không ra.
 * Khác `jpSheetId_` ở chỗ nó **kiểm tra đọc được thật** trước khi chốt.
 *
 * ID không nằm trong code (repo đẩy lên git, ID lọt vào history là vĩnh viễn).
 */
function jpDatCauHinh_(sheetId) {
  var p = PropertiesService.getScriptProperties();
  var dangCo = p.getProperty('JP_SHEET_ID');
  var id = jpStr_(sheetId);
  var nguon = 'truyền tay';

  if (!id) {
    var tim = jpDoSheetId_();
    if (!tim.id) throw new Error(tim.msg);
    id = tim.id;
    nguon = 'tự dò theo tên';
  }

  /* Ghi tạm rồi thử đọc QUA LỚP CORE. Sai sheet thì trả lại giá trị cũ. */
  p.setProperty('JP_SHEET_ID', id);
  try {
    jpResetKetNoi_();
    var soUser = jpRows_(JP_TABS.USERS).length;
    var soLoc  = jpRows_(JP_TABS.LOCATIONS).length;

    var msg = 'Đã đặt JP_SHEET_ID (' + nguon + '): ' + id +
              '\n  · ' + soUser + ' tài khoản · ' + soLoc + ' cơ sở' +
              (dangCo && dangCo !== id ? '\n  · thay giá trị cũ: ' + dangCo : '');
    Logger.log(msg);
    return msg;
  } catch (e) {
    if (dangCo) p.setProperty('JP_SHEET_ID', dangCo);
    else p.deleteProperty('JP_SHEET_ID');
    throw new Error('ID ' + id + ' không đọc được như sheet dữ liệu JP (' +
                    e.message + '). Đã trả lại cấu hình cũ.');
  }
}

/*
 * ⚠️ VÌ SAO CẢ CỤM HÀM DƯỚI ĐÂY CÓ DẤU `_` (soát lại 04/08/2026)
 *
 * Web app chạy ở `ANYONE_ANONYMOUS` — ai có link cũng mở được trang, và mở được
 * trang thì gọi được `google.script.run.<tên hàm>()` từ console, KHÔNG cần đăng
 * nhập. Cửa chắn duy nhất của app là màn hình đăng nhập, mà mấy hàm này đi vòng
 * qua nó hoàn toàn. Cụ thể trước khi sửa:
 *
 *   · `jpDatCauHinh(sheetId)` ghi thẳng `JP_SHEET_ID` ⇒ người lạ TRỎ CẢ APP sang
 *     sheet của họ, tự đặt PIN trong sheet đó rồi đăng nhập. Nặng nhất.
 *   · `jpKhoiTao()` trả về chuỗi có sẵn chữ "Kế toán PIN 222" ⇒ đọc được PIN
 *     mặc định mà không cần gì cả. PIN chỉ 3 số, kế toán mặc định đúng 222.
 *   · `jpSetup()` tạo tài khoản khi bảng người dùng rỗng, và cũng trả PIN ra.
 *   · `jpNapCoSoJP()` / `jpSeedDemo()` ghi danh mục vào sheet thật.
 *   · `jpHealthCheck()` đọc ra số dòng mọi tab và id báo cáo.
 *
 * Dấu `_` cuối tên là cách Apps Script chặn `google.script.run`. Trình chỉnh sửa
 * VẪN chạy được các hàm này bình thường — chúng vốn để chạy tay từ đó.
 *
 * Muốn gọi từ web thì viết một hàm bọc CÓ `token` và `jpNeedKT_`, như
 * `jpKiemTraNhanh` bên dưới. Đừng bỏ dấu `_` đi.
 */
function jpKhoiTao_() {
  var log = [];
  log.push(jpSetup_());
  log.push('---- TỰ KIỂM CÔNG THỨC ----');
  log.push(jpSelfTest_());
  Logger.log(log.join('\n'));
  return log.join('\n');
}

/*
 * 13 CƠ SỞ JP ĐANG CHẠY THẬT — lấy từ file DAILY SALES MISA REPORT tháng 7/2026
 * (Andy chốt 04/08/2026: dùng đúng danh sách này, không dùng 18 dòng mapping cũ).
 *
 * `unitCode` giữ nguyên số đầu vì đó là mã đơn vị trong MISA; Unit ID trong báo cáo
 * là phần sau khi bỏ số. Số đầu = vùng, nên đừng "dọn" nó đi.
 *
 * `maKH` = Mã đối tượng trong sổ MISA. Ô trống là chưa tra được — kế toán bổ sung
 * ở Cấu hình → Cơ sở, thiếu thì đối soát không khớp được theo mã KH.
 */
/*
 * Cột `loaiMay` lấy từ file **`Tồn kho JP đến 31.7`** của kế toán (Andy gửi
 * 04/08/2026: *"trong đó anh phân máy tiền và xu luôn rồi"*). Trong file đó mỗi kho
 * MISA là một khối, và **tên kho ghi luôn loại máy** — `JP Aeon Mall Tân Phú- Máy xu`.
 * Đó là nguồn duy nhất cho việc phân loại máy, đừng đoán lại từ đâu khác.
 *
 * Chỉ **8 trong 13** cơ sở có kho trong file đó. Andy xác nhận 04/08/2026: *"mấy cái
 * không có là dẹp rồi đó em"* ⇒ năm cơ sở còn lại (ETL · VHM · LM · KWZ · SRBD) **đã
 * ngưng hoạt động**, cột `hoatDong` để `'N'`. Đúng 5 cơ sở đó cũng là 5 cơ sở thiếu
 * `maKH` — khớp nhau, không phải trùng hợp.
 *
 * ⚠️ **`hoatDong = 'N'` chỉ chặn việc MỚI, KHÔNG viết lại quá khứ.** Cả 5 cơ sở này
 * ĐỀU có doanh thu tháng 7/2026 trong DAILY SALES, nên bản xuất MISA vẫn phải có
 * chúng — `jpBaoCaoDoanhThuNgay` đã sửa để cơ sở đã ngưng mà có phát sinh trong kỳ
 * thì vẫn lên bảng. Đừng "dọn" thành lọc thẳng `active !== 'N'` ở đó.
 *
 * `KHOMN` (Kho Miền Nam) trong file KHÔNG phải cơ sở — đó là **kho tổng**
 * (`JP_KHO_TONG = 'TONG'`). Đừng thêm nó vào danh sách này.
 */
var JP_CO_SO_2026 = [
  /* unitCode      code       tên đầy đủ                        maKH       khu vực         loaiMay          hoatDong  bcMau           dhTrung */
  /* Cột cuối: `'N'` = máy KHÔNG có đồng hồ đếm trứng ⇒ ẩn hai ô `hBefore`/`hAfter`
     bên hàng hoá. Để TRỐNG là chưa biết ⇒ `jpNapCoSoJP_` KHÔNG đụng tới, giữ nguyên
     cái kế toán đã tự đặt — cùng luật với `loaiMay` và `bcMau`.
     ⚠️ Andy chốt 15/08/2026: **MỌI cơ sở máy TIỀN** đều không có đồng hồ đếm trứng,
     không riêng VinWonders. Máy XU để trống vì bảng của nó (`tableCoin`) vốn không có
     hai ô đó — đặt `'N'` cho máy xu là khai một thứ không tồn tại. */
  ['50JPETL',     'ETL',     'JP MN ESTELLA',                   '',        'HO CHI MINH',  '',            'N',      ''],
  ['50JPAMBT',    'AMBT',    'JP MN AEON MALL BÌNH TÂN',        'KH00129', 'HO CHI MINH',  JP_TYPE_MONEY, 'Y',      '',             'N'],
  ['50JPVHM',     'VHM',     'JP MN VẠN HẠNH MALL',             '',        'HO CHI MINH',  '',            'N',      ''],
  ['50JPAMTP',    'AMTP',    'JP MN AEON MALL TÂN PHÚ',         'KH00119', 'HO CHI MINH',  JP_TYPE_COIN,  'Y',      ''],
  ['50JPLM',      'LM',      'JP MN LANDMARK',                  '',        'HO CHI MINH',  '',            'N',      ''],
  ['50JPSCVV',    'SCVV',    'JP MN SC VIVO',                   'KH00134', 'HO CHI MINH',  JP_TYPE_COIN,  'Y',      ''],
  ['50JPVC3/2',   'VC32',    'JP MN VICOM 3/2',                 'KH00137', 'HO CHI MINH',  JP_TYPE_COIN,  'Y',      ''],
  ['50JPKWZ',     'KWZ',     'JP MN KIWOOZA',                   '',        'HO CHI MINH',  '',            'N',      ''],
  ['51JPAMBD',    'AMBD',    'JP MN AEON MALL BÌNH DƯƠNG',      'KH00108', 'BINH DUONG',   JP_TYPE_MONEY, 'Y',      '',             'N'],
  ['51JPSRBD',    'SRBD',    'JP MN SORA BÌNH DƯƠNG',           '',        'BINH DUONG',   '',            'N',      ''],
  ['52JPVWPQ',    'VWPQ',    'JP MN VINWONDER PHÚ QUỐC',        'KH00143', 'PHU QUOC',     JP_TYPE_MONEY, 'Y',      '',             'N'],
  ['53JPSWPQ',    'SWPQ',    'JP MN SUNWORLD PHÚ QUỐC',         'KH00142', 'PHU QUOC',     JP_TYPE_MONEY, 'Y',      '',             'N'],
  /* SÂN BAY PHÚ QUỐC — mẫu TÁCH tiền / hàng (Andy chốt 04/08/2026). Cơ sở này có 54
     mã hàng trong Tồn kho 31/7, nhiều hơn số ô máy, nên hàng không gắn 1-1 vào ô. */
  ['53JPSBPQ',    'SBPQ',    'JP MN SÂN BAY PHÚ QUỐC',          'KH00207', 'PHU QUOC',     JP_TYPE_MONEY, 'Y',      JP_BC_MAU_TACH, 'N']
];

/* `JP_KHU_VUC` đã chuyển sang `00_Config` — nó là hằng số của bản xuất MISA, chỉ
   `08_LienThongKeToan` dùng, để ở đây là sai chỗ theo kiến trúc "00_Config giữ hằng số". */

/**
 * Nạp / cập nhật 13 cơ sở JP. Chạy tay, an toàn khi chạy lại.
 *
 * Khớp theo `unitCode`, không khớp thì thử `code` — nên cơ sở anh đã tạo tay
 * (AMTP, VINPQ) được BỔ SUNG mã KH/vùng chứ không bị tạo trùng.
 * KHÔNG đụng `machineType`, `photoDefault`, `active` của cơ sở đã có.
 */
function jpNapCoSoJP_() {
  var co = jpRows_(JP_TABS.LOCATIONS);
  var theoUnit = {}, theoCode = {};
  co.forEach(function (r) {
    if (jpStr_(r.unitCode)) theoUnit[jpStr_(r.unitCode)] = r;
    if (jpStr_(r.code)) theoCode[jpStr_(r.code).toUpperCase()] = r;
  });

  var them = 0, capNhat = [], out = [];
  var daNgung = [];
  JP_CO_SO_2026.forEach(function (a) {
    var unitCode = a[0], code = a[1], ten = a[2], maKH = a[3], vung = a[4];
    var loaiMay = a[5] || '';
    var hoatDong = a[6] === 'N' ? 'N' : 'Y';
    var mau = a[7] || '';
    var dhTrung = a[8] || '';
    var cu = theoUnit[unitCode] || theoCode[code.toUpperCase()];

    if (hoatDong === 'N') daNgung.push(code);

    if (cu) {
      var f = { unitCode: unitCode, khuVuc: vung, name: ten, active: hoatDong };
      if (maKH) f.maKH = maKH;
      /* Ghi ĐÈ loại máy khi đã biết: bản trước gán cứng `JP_TYPE_MONEY` cho mọi cơ sở
         mới, nên 3 cơ sở máy XU (AMTP · SCVV · VC32) đang sai. Chưa biết thì KHÔNG
         đụng — giữ nguyên cái kế toán đã tự đặt tay. */
      if (loaiMay) f.machineType = loaiMay;
      /* Mẫu báo cáo: cùng một luật với loại máy — biết thì ghi đè, KHÔNG biết thì
         không đụng. Ghi `''` cho mọi cơ sở là xoá mẫu kế toán đã tự bật cho một điểm
         mới, và nhân viên ở đó mất bảng hàng ngay kỳ sau. */
      if (mau) f.bcMau = mau;
      /* Đồng hồ đếm trứng: y luật trên — biết thì ghi, TRỐNG thì không đụng. Ghi `'Y'`
         cho mọi cơ sở là xoá mất việc kế toán đã tự tắt cột cho một điểm không có đồng
         hồ, và nhân viên ở đó lại thấy hai ô không bao giờ điền được. */
      if (dhTrung) f.coDhTrung = dhTrung;
      jpFields_(JP_TABS.LOCATIONS, cu._row, f);
      capNhat.push(code + (loaiMay ? ' [' + loaiMay + ']' : '') +
                   (mau ? ' [mẫu tách]' : '') +
                   (dhTrung === 'N' ? ' [không đồng hồ trứng]' : '') +
                   (hoatDong === 'N' ? ' (ngưng)' : ''));
    } else {
      jpAppend_(JP_TABS.LOCATIONS, {
        id: jpNextId_('L'), code: code, name: ten, maKH: maKH,
        unitCode: unitCode, khuVuc: vung,
        machineType: loaiMay, photoDefault: 1, active: hoatDong,
        bcMau: mau,
        /* ⚠️ Nhánh TẠO MỚI phải ghi cờ y như nhánh cập nhật. Bản đầu chỉ sửa nhánh cập
           nhật, mà sheet trống thì mọi cơ sở đi qua ĐÂY ⇒ VWPQ không bao giờ được tắt.
           `day-chuyen.js` bắt đúng chỗ này (nó chạy từ sheet trống hoàn toàn). */
        coDhTrung: dhTrung,
        note: 'Nạp từ DAILY SALES 07/2026' +
              (loaiMay ? ' · loại máy từ Tồn kho 31/7' : '') +
              (mau ? ' · mẫu tách tiền/hàng' : '') +
              (dhTrung === 'N' ? ' · máy không có đồng hồ đếm trứng' : '') +
              (hoatDong === 'N' ? ' · đã dẹp, không có kho ở Tồn kho 31/7' : '')
      });
      them++;
    }
    /* Cơ sở đã ngưng thì thiếu mã KH cũng không làm gì được nữa — đừng nhắc, kẻo
       cảnh báo dài ra vì những điểm đã đóng. */
    if (!maKH && hoatDong !== 'N') out.push(code);
  });

  var msg = 'Đã nạp ' + them + ' cơ sở mới, cập nhật ' + capNhat.length +
            ' cơ sở có sẵn (' + capNhat.join(', ') + ').' +
            (out.length ? '\n⚠ Thiếu mã KH — kế toán bổ sung: ' + out.join(', ') : '') +
            (daNgung.length ? '\n· Đã đặt NGƯNG cho ' + daNgung.length + ' cơ sở dẹp: ' +
                              daNgung.join(', ') +
                              '\n  (doanh thu cũ của họ VẪN lên bản xuất MISA — ngưng chỉ' +
                              ' chặn báo cáo mới)' : '');
  Logger.log(msg);
  return msg;
}

/** Dữ liệu mẫu theo đúng 2 cơ sở thật để thử luồng ngay. */
function jpSeedDemo_() {
  if (jpRows_(JP_TABS.LOCATIONS).length) return 'Đã có cơ sở, bỏ qua.';

  /* --- AEON Tân Phú: máy XU, 2 cụm có QR --- */
  var l1 = jpNextId_('L');
  jpAppend_(JP_TABS.LOCATIONS, {
    id: l1, code: 'AMTP', name: 'AEON Tân Phú', maKH: 'KH-AMTP',
    machineType: JP_TYPE_COIN, photoDefault: 2, active: 'Y', note: ''
  });
  [['JP AMTP 01', 'VVB920429'], ['JP AMTP 02', 'VVB571830']].forEach(function (c) {
    var cid = jpNextId_('C');
    jpAppend_(JP_TABS.CLUSTERS, {
      id: cid, locationId: l1, name: c[0], payboxSerial: c[1],
      hasQR: 'Y', photoCount: 1, active: 'Y', note: ''
    });
    jpAppend_(JP_TABS.MACHINES, {
      id: jpNextId_('M'), locationId: l1, clusterId: cid, code: c[0] + ' - Ô 1',
      itemCode: '', itemMisa: '', photoCount: 1, active: 'Y', note: ''
    });
  });

  /* --- VinWonders Phú Quốc: máy TIỀN, 3 khu vực --- */
  var l2 = jpNextId_('L');
  jpAppend_(JP_TABS.LOCATIONS, {
    id: l2, code: 'VINPQ', name: 'VinWonders Phú Quốc', maKH: 'KH-VINPQ',
    machineType: JP_TYPE_MONEY, photoDefault: 1, active: 'Y', note: ''
  });
  ['Thủy Cung', 'Thủy Cung tầng 2', 'Khu Game'].forEach(function (n) {
    jpAppend_(JP_TABS.CLUSTERS, {
      id: jpNextId_('C'), locationId: l2, name: n, payboxSerial: '',
      hasQR: 'N', photoCount: 0, active: 'Y', note: ''
    });
  });

  /* --- Danh mục hàng mẫu (giá tự suy từ tiền tố mã) --- */
  [['100JP023', 'Shin siêu nhân'], ['100JP049', 'B003 - Lân'],
   ['150JP119', 'C - Lucky Capibara'], ['100JP122', 'B - Chuột nước'],
   ['150JP105', 'C - Mavel'], ['100JP121', 'B - Capipara'],
   ['150JP118', 'Ngựa'], ['100JP051', 'B001']
  ].forEach(function (i) {
    jpAppend_(JP_TABS.ITEMS, {
      code: i[0], misa: i[0], name: i[1], price: jpGiaTuMa_(i[0]),
      active: 'Y', note: ''
    });
  });

  var nv = jpRows_(JP_TABS.USERS).filter(function (u) {
    return jpStr_(u.role) === JP_ROLE_NV;
  })[0];
  if (nv) jpFields_(JP_TABS.USERS, nv._row, { locationIds: l1 + ',' + l2 });

  return 'Đã tạo 2 cơ sở mẫu, 5 cụm, 8 mã hàng.';
}

/*──────────────────── ③ CHẨN ĐOÁN NHANH ────────────────────*/

function jpHealthCheck_() {
  var out = [];
  Object.keys(JP_TABS).forEach(function (k) {
    var d = JP_TABS[k];
    out.push(d.name + ': ' + jpRows_(d).length + ' dòng');
  });

  var bad = [];
  jpRows_(JP_TABS.ROWS).forEach(function (r) {
    var price = jpNum_(r.price);
    if (!price && jpStr_(r.itemCode)) {
      bad.push('Dòng ' + r.id + ' mã "' + r.itemCode + '" không có giá');
    }
  });

  var orphanRows = 0, ids = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (h) { ids[String(h.id)] = 1; });
  jpRows_(JP_TABS.ROWS).forEach(function (r) {
    if (!ids[String(r.reportId)]) orphanRows++;
  });

  out.push('--- Vấn đề ---');
  out.push('Dòng thiếu giá: ' + bad.length);
  out.push('Dòng mồ côi (mất báo cáo cha): ' + orphanRows);
  bad.slice(0, 10).forEach(function (b) { out.push('  · ' + b); });

  Logger.log(out.join('\n'));
  return out.join('\n');
}


/*──────────────────── CỬA CÓ KIỂM QUYỀN CHO WEB KẾ TOÁN ────────────────────*/

/**
 * Chạy lại toàn bộ công thức tiền bằng số thật — nút "Kiểm tra nhanh" ở
 * Đối soát → ⑤. Chỉ đọc, không ghi ô nào.
 *
 * Là hàm BỌC của `jpSelfTest_`. Bản thân `jpSelfTest_` chỉ tính trên số cứng
 * trong code nên lộ ra cũng không mất gì, nhưng cứ để một cửa duy nhất cho dễ
 * soát: hàm nào web gọi được thì hàm đó có `token`.
 */
function jpKiemTraNhanh(token) {
  jpNeedKT_(jpAuth_(token));
  return jpSelfTest_();
}

/**
 * Số liệu sức khoẻ hệ thống cho kế toán. Bọc của `jpHealthCheck_`.
 * Số dòng từng tab + các dòng thiếu giá + dòng mồ côi — hữu ích khi đi tìm lỗi,
 * nhưng là dữ liệu nội bộ nên phải đăng nhập mới xem được.
 */
function jpSucKhoeHeThong(token) {
  jpNeedKT_(jpAuth_(token));
  return jpHealthCheck_();
}

/**
 * Nạp / cập nhật 13 cơ sở JP — bản BỌC của `jpNapCoSoJP_` cho web kế toán.
 *
 * Trước bản này muốn nạp phải mở trình chỉnh sửa Apps Script chạy hàm tay, mà đó đúng
 * là bước bắt buộc phải làm TRƯỚC khi cấp mã PIN cơ sở (PIN lấy loại máy theo cơ sở).
 * Bắt kế toán vào editor cho một bước trong luồng dựng hệ thống là chỗ dễ bỏ sót nhất.
 *
 * Vẫn giữ `jpNapCoSoJP_` có dấu `_`: hàm gốc ghi thẳng danh mục nên KHÔNG được để
 * `google.script.run` gọi trần — web ở `ANYONE_ANONYMOUS`. Cửa duy nhất là hàm này,
 * có `token` và `jpNeedKT_`.
 */
function jpNapCoSo(token) {
  var u = jpNeedKT_(jpAuth_(token));
  var msg = jpNapCoSoJP_();
  jpAudit_(u, 'CFG_NAP_CO_SO', '', '', { soCoSo: JP_CO_SO_2026.length });
  return { ok: true, msg: msg };
}

/**
 * TÌNH TRẠNG DỰNG HỆ THỐNG — cho ô "Việc cần làm để chạy được" ở màn Tổng quan.
 *
 * Vì sao có hàm này: các bước dựng hệ thống (đổi PIN → nạp cơ sở → danh mục hàng →
 * cấp PIN cơ sở → khai tồn đầu kỳ) phải làm ĐÚNG THỨ TỰ, mà thứ tự đó trước giờ chỉ
 * nằm trong tin nhắn. Tin nhắn sẽ cũ; ô trên màn hình thì **dò từ dữ liệu thật** nên
 * lúc nào cũng đúng.
 *
 * Chỉ ĐỌC, không ghi ô nào. Gộp mọi con số vào MỘT lượt gọi thay vì để giao diện gọi
 * 4–5 hàm — mỗi lượt `google.script.run` là một vòng mạng.
 */
function jpTinhTrangDungHeThong(token) {
  jpNeedKT_(jpAuth_(token));

  /* ── PIN mặc định còn ai dùng? ──
     `jpAuth_` chặn mọi cửa khi phiên có cờ `phaiDoiPin`, nên đây là việc số 1. */
  var conPinMacDinh = [];
  JP_PIN_MAC_DINH.forEach(function (pin) {
    var u = jpPinDaDung_(pin, '');
    if (u) conPinMacDinh.push(jpStr_(u.username) || jpStr_(u.hoTen));
  });

  /* ── Cơ sở ── */
  var locs = jpRows_(JP_TABS.LOCATIONS);
  var dangChay = locs.filter(function (l) { return jpStr_(l.active) !== 'N'; });
  var thieuLoaiMay = dangChay.filter(function (l) { return !jpStr_(l.machineType); });
  var thieuMaKH = dangChay.filter(function (l) { return !jpStr_(l.maKH); });

  /* ── Cơ sở THIẾU MÃ ĐƠN VỊ ──
     Mọi cơ sở thật đều phải có `unitCode` (mã đơn vị MISA); không có thì
     `jpBaoCaoDoanhThuNgay` không đặt nó vào vùng nào được và nó chỉ hiện ra dưới dạng
     một dòng cảnh báo. Đây cũng là dấu hiệu chắc nhất của **cơ sở demo còn sót**:
     `jpSeedDemo_` tạo `AMTP`/`VINPQ` với `maKH` giả (`KH-AMTP`) và không có unitCode.

     Vì sao dò theo `unitCode` chứ không theo "có nằm trong `JP_CO_SO_2026` hay không":
     kế toán mở điểm mới thì cơ sở đó CHƯA nằm trong bảng cứng, mà nó vẫn hợp lệ —
     dò theo danh sách cứng là nhắc nhầm mãi. Còn thiếu unitCode thì đúng là phải sửa,
     dù cơ sở mới hay cơ sở sót. */
  var chuanTheoCode = {};
  JP_CO_SO_2026.forEach(function (a) { chuanTheoCode[a[1].toUpperCase()] = a[0]; });
  var thieuUnit = dangChay.filter(function (l) { return !jpStr_(l.unitCode); });

  /* ── Danh mục hàng ── */
  var soMaHang = jpRows_(JP_TABS.ITEMS).filter(function (i) {
    return jpStr_(i.active) !== 'N';
  }).length;

  /* ── Tài khoản cơ sở: cơ sở đang chạy nào chưa có tài khoản `cs<mã>` có PIN ── */
  var users = jpRows_(JP_TABS.USERS);
  var coTk = {};
  users.forEach(function (u) {
    if (jpStr_(u.pin) && jpStr_(u.active) !== 'N') coTk[jpStr_(u.username).toLowerCase()] = 1;
  });
  var chuaCoPin = dangChay.filter(function (l) {
    var uname = ('cs' + jpStr_(l.code)).toLowerCase().replace(/[^a-z0-9]/g, '');
    return !coTk[uname];
  }).map(function (l) { return jpStr_(l.code); });

  /* ── CƠ SỞ CÓ TIỀN QR MÀ CHƯA CÓ CỤM NHẬN QR ──
   *
   * Dải ảnh **Pay Box / QR** gắn theo CỤM. Cơ sở không có cụm nào mang `hasQR` thì ô
   * "— chọn cụm —" trong form **rỗng hoàn toàn** ⇒ nhân viên không có chỗ nào gắn ảnh
   * Pay Box, và **không ai được báo**. Đo trên sheet thật 08/09/2026: SBPQ là cơ sở
   * máy tiền DUY NHẤT có 0 cụm, trong khi 9 báo cáo của nó đều có tiền QR — và đúng ở
   * đó `RP20260824-0055` lệch 1,52 triệu vì QR.
   *
   * ⚠️ Bước này là tầng CẤU HÌNH (*cụm có tồn tại không*), khác hẳn `W17` là tầng BÁO
   * CÁO (*khu đã chọn cụm chưa*). Cố ý tách: W17 im lặng khi cơ sở chưa có cụm nào —
   * kêu nhân viên lúc họ **không có gì để chọn** là kêu sai người. Chỗ nói đúng người
   * là đây, vì chỉ kế toán mới thêm được cụm.
   *
   * ⚠️ Đo "có QR" bằng `revBank` trên HEAD, không quét `JP_Rows`: `revBank` là Σ `bank`
   * do `jpCalcReport_` chốt sẵn, mà `JP_Rows` có mấy nghìn dòng và bảng này chỉ có
   * ~200 dòng/năm.
   *
   * ⚠️ Chỉ tính cơ sở ĐANG CHẠY và cụm ĐANG DÙNG — cơ sở dẹp rồi thì không cần cụm,
   * và cụm `active = 'N'` là cụm đã bỏ, để nó tính là "đã có" thì bước này xanh giả.
   */
  var coCumQR = {};
  jpRows_(JP_TABS.CLUSTERS).forEach(function (c) {
    if (jpStr_(c.active) === 'N') return;
    if (jpStr_(c.hasQR).toUpperCase() !== 'Y') return;
    coCumQR[String(c.locationId)] = 1;
  });
  var coTienQR = {};
  jpRows_(JP_TABS.REPORTS).forEach(function (r) {
    if (jpNum_(r.revBank) > 0) coTienQR[String(r.locationId)] = 1;
  });
  var thieuCumQR = dangChay.filter(function (l) {
    return coTienQR[String(l.id)] && !coCumQR[String(l.id)];
  }).map(function (l) { return jpStr_(l.code); });

  /* ── Tồn đầu kỳ: đếm theo KHO có lớp tồn, kho tổng tính riêng ── */
  var tonTheoKho = {}, tongTon = 0;
  jpRows_(JP_TABS.KHO_LOP).forEach(function (l) {
    var kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    var q = jpNum_(l.qtyRemaining);
    if (q <= 0) return;
    tonTheoKho[kho] = (tonTheoKho[kho] || 0) + q;
    tongTon += q;
  });
  var soKhoCoTon = Object.keys(tonTheoKho).length;

  /* Bước nào XONG thì `xong = true`. Thứ tự trong mảng CHÍNH LÀ thứ tự phải làm —
     nạp cơ sở phải trước cấp PIN vì PIN lấy loại máy theo cơ sở. */
  var buoc = [
    { id: 'pin', ten: 'Đổi PIN mặc định của kế toán',
      xong: conPinMacDinh.length === 0,
      chiTiet: conPinMacDinh.length
        ? 'Còn dùng PIN mặc định: ' + conPinMacDinh.join(', ') + ' — mọi cửa bị khoá cho tới khi đổi'
        : 'Không còn tài khoản nào dùng PIN mặc định',
      mod: '', tab: '', sub: '' },

    { id: 'coso', ten: 'Nạp danh mục cơ sở + loại máy',
      xong: dangChay.length > 0 && thieuLoaiMay.length === 0,
      chiTiet: !dangChay.length ? 'Chưa có cơ sở nào'
        : dangChay.length + ' cơ sở đang chạy' +
          (locs.length - dangChay.length ? ' · ' + (locs.length - dangChay.length) + ' đã ngưng' : '') +
          (thieuLoaiMay.length
            ? ' · ⚠ ' + thieuLoaiMay.length + ' cơ sở chưa rõ loại máy: ' +
              thieuLoaiMay.map(function (l) { return jpStr_(l.code); }).join(', ')
            : ' · loại máy đủ hết'),
      mod: 'cf', tab: 'cfL', sub: '' },

    { id: 'hang', ten: 'Nạp danh mục hàng hoá',
      xong: soMaHang > 0,
      chiTiet: soMaHang ? soMaHang + ' mã hàng đang dùng' : 'Chưa có mã hàng nào',
      mod: 'cf', tab: 'cfI', sub: '' },

    { id: 'pincs', ten: 'Cấp mã PIN cho từng cơ sở',
      xong: dangChay.length > 0 && chuaCoPin.length === 0,
      chiTiet: !dangChay.length ? 'Làm bước nạp cơ sở trước'
        : chuaCoPin.length
          ? chuaCoPin.length + ' cơ sở chưa có tài khoản + PIN: ' + chuaCoPin.join(', ')
          : 'Cả ' + dangChay.length + ' cơ sở đều đã có tài khoản riêng',
      mod: 'cf', tab: 'cfU', sub: '' },

    { id: 'unit', ten: 'Cơ sở đang chạy phải có mã đơn vị MISA',
      xong: dangChay.length > 0 && thieuUnit.length === 0,
      chiTiet: !dangChay.length ? 'Làm bước nạp cơ sở trước'
        : !thieuUnit.length ? 'Cả ' + dangChay.length + ' cơ sở đều có mã đơn vị'
        : thieuUnit.length + ' cơ sở thiếu mã đơn vị: ' +
          thieuUnit.map(function (l) {
            var code = jpStr_(l.code);
            /* Mã KHÔNG có trong danh mục chuẩn ⇒ gần như chắc là cơ sở demo còn sót
               (hoặc điểm mới chưa khai). Nói rõ để kế toán biết nên NGƯNG hay bổ sung. */
            return code + (chuanTheoCode[code.toUpperCase()]
              ? ' (có trong danh mục — bấm Nạp cơ sở là xong)'
              : ' (KHÔNG có trong danh mục 13 cơ sở — nếu là cơ sở demo/trùng thì bỏ tick' +
                ' Đang dùng, ĐỪNG xoá dòng kẻo báo cáo cũ thành mồ côi)');
          }).join(' · '),
      mod: 'cf', tab: 'cfL', sub: '' },

    { id: 'cumqr', ten: 'Cơ sở có thu QR phải có cụm nhận QR',
      xong: thieuCumQR.length === 0,
      chiTiet: !thieuCumQR.length
        ? (Object.keys(coTienQR).length
            ? 'Mọi cơ sở có thu QR đều đã có cụm nhận QR'
            : 'Chưa cơ sở nào phát sinh tiền QR')
        : thieuCumQR.length + ' cơ sở có tiền QR mà CHƯA có cụm nào nhận QR: ' +
          thieuCumQR.join(', ') +
          ' — nhân viên không có chỗ nào gắn ảnh Pay Box, và báo cáo vẫn nộp được.' +
          ' Thêm ở Cấu hình → Cụm máy: chọn cơ sở, đặt tên cụm theo tên gate, tick "Có QR".' +
          ' ⚠ Bước này phải làm TAY — nút "Làm hết bằng 1 lần bấm" không đặt hộ tên cụm được.',
      mod: 'cf', tab: 'cfC', sub: '' },

    { id: 'ton', ten: 'Khai tồn đầu kỳ cho kho tổng và từng cơ sở',
      xong: soKhoCoTon > 0,
      chiTiet: soKhoCoTon
        ? soKhoCoTon + ' kho đã có tồn · tổng ' + tongTon + ' cái' +
          (tonTheoKho[JP_KHO_TONG] ? '' : ' · ⚠ KHO TỔNG chưa khai')
        : 'Chưa khai tồn kho nào — sổ giá vốn 632 sẽ thiếu lớp',
      mod: 'kho', tab: 'khCt', sub: 'khDauKy' }
  ];

  var xong = buoc.filter(function (b) { return b.xong; }).length;

  return {
    ok: true, buoc: buoc, soXong: xong, soBuoc: buoc.length,
    sanSang: xong === buoc.length,
    /* Mấy thứ nên soát nhưng KHÔNG chặn — thiếu mã KH thì đối soát không khớp được
       theo mã KH, nhưng hệ thống vẫn chạy. Đừng nhập chung vào danh sách bước. */
    nhacThem: thieuMaKH.length
      ? [thieuMaKH.length + ' cơ sở đang chạy chưa có mã KH (' +
         thieuMaKH.map(function (l) { return jpStr_(l.code); }).join(', ') +
         ') — đối soát QR/TM/CK sẽ không khớp được theo mã KH']
      : []
  };
}

