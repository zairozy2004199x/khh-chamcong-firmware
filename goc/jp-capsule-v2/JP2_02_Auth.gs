/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB NHÂN VIÊN  ·  02_Auth
 * ---------------------------------------------------------------------------
 * Đăng nhập bằng MÃ PIN · phiên làm việc · kiểm tra quyền.
 *
 * 2 vai trò: NHANVIEN · KETOAN (chốt 04/08/2026 — trước đây tách KT doanh thu
 * và KT kho, nay gộp một). Mọi hàm nghiệp vụ bắt đầu bằng jpAuth_(token).
 *═══════════════════════════════════════════════════════════════════════════*/

var JP_SESSION_HOURS = 12;

/**
 * CacheService chặn trên 6 giờ (21600s). Truyền quá thì Apps Script tự cắt,
 * nên phiên 12 giờ trước đây chết sớm giữa ca mà không ai hiểu vì sao.
 * Cách xử lý: TTL cache 6 giờ, mỗi lần gọi jpAuth_ gia hạn lại (phiên trượt),
 * còn mốc chết tuyệt đối 12 giờ vẫn nằm trong payload.
 */
var JP_CACHE_MAX_SEC = 21600;

/*──────────────────── ① BĂM VÀ SO KHỚP ────────────────────*/

/*
 * Dạng lưu:  sha256$<salt>$<hex>  — KHÔNG bao giờ lưu chuỗi thật.
 * Dùng cho cả PIN. Giá trị chưa băm (ai gõ thẳng vào sheet) vẫn so khớp được,
 * `jpCheckPass_` trả `legacy: true` để chỗ gọi băm lại ngay tại chỗ.
 *
 * Tên hàm vẫn là *Pass_ dù giờ chỉ dùng cho PIN — đổi tên thì phải sửa rải rác,
 * không đáng.
 */

function jpHashPass_(pass, salt) {
  var raw = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256, salt + '::' + String(pass), Utilities.Charset.UTF_8);
  return raw.map(function (b) {
    return ('0' + (b & 0xFF).toString(16)).slice(-2);
  }).join('');
}

/** Băm mật khẩu mới, kèm salt riêng cho từng tài khoản. */
function jpMakePass_(pass) {
  var salt = Utilities.getUuid().replace(/-/g, '').slice(0, 16);
  return 'sha256$' + salt + '$' + jpHashPass_(pass, salt);
}

/** Trả {ok, legacy} — legacy = bản ghi còn lưu mật khẩu thuần, cần băm lại. */
function jpCheckPass_(stored, pass) {
  var s = jpStr_(stored), p = jpStr_(pass);
  var m = s.match(/^sha256\$([^$]+)\$([0-9a-f]+)$/);
  if (m) return { ok: jpHashPass_(p, m[1]) === m[2], legacy: false };
  return { ok: !!s && s === p, legacy: true };
}

/*──────────────────── ①b MÃ PIN ────────────────────*/

/*
 * Đăng nhập CHỈ bằng PIN (Andy chốt 04/08/2026), nên PIN phải duy nhất toàn hệ
 * thống — `jpPinDaDung_` chặn trùng lúc tạo tài khoản.
 *
 * ⚠️ PIN 3 số = 1000 khả năng, mà web app ở `ANYONE_ANONYMOUS`. Băm chỉ chống
 * người đọc được sheet, KHÔNG chống dò: 1000 giá trị thì thử hết là ra. Chốt chặn
 * duy nhất khả thi ở đây là làm chậm và ghi lại — xem `jpGhiNhanSai_`.
 * Muốn bảo vệ thật thì phải đổi `access` của web app, không phải đổi code.
 */

function jpChuanPin_(pin) {
  var s = jpStr_(pin).replace(/\D/g, '');
  return s.length === JP_PIN_LEN ? s : '';
}

/** PIN này đã có tài khoản nào dùng chưa? Trả về bản ghi đó, hoặc null. */
function jpPinDaDung_(pin, boQuaId) {
  var p = jpChuanPin_(pin);
  if (!p) return null;
  return jpRows_(JP_TABS.USERS).filter(function (r) {
    if (boQuaId && String(r.id) === String(boQuaId)) return false;
    return jpStr_(r.pin) && jpCheckPass_(r.pin, p).ok;
  })[0] || null;
}

/*──────── Làm chậm khi gõ sai, và ghi lại ────────*/

var JP_FAIL_KEY = 'JPPINFAIL';
var JP_FAIL_WINDOW = 600;      // đếm số lần sai trong 10 phút

/**
 * Mỗi lần sai thì chờ lâu thêm một nhịp, tối đa 5 giây, và ghi audit.
 *
 * CỐ Ý KHÔNG khoá cứng toàn hệ thống: khoá cứng là tự tay đưa cho người ngoài
 * cách làm cả cửa hàng không đăng nhập được. Làm chậm thì kẻ dò tuần tự mất hàng
 * chục phút, còn nhân viên gõ sai vài lần gần như không thấy gì.
 */
function jpGhiNhanSai_() {
  var c = CacheService.getScriptCache();
  var n = parseInt(c.get(JP_FAIL_KEY) || '0', 10) + 1;
  c.put(JP_FAIL_KEY, String(n), JP_FAIL_WINDOW);

  if (n >= 5) {
    Utilities.sleep(Math.min(5000, 500 * n));
    if (n === 5 || n % 10 === 0) {
      jpAudit_(null, 'PIN_SAI_NHIEU', '', '',
               { soLanSaiTrong10Phut: n, canhBao: 'có thể đang bị dò PIN' });
    }
  }
  return n;
}

/*──────────────────── ② ĐĂNG NHẬP ────────────────────*/

/**
 * Đăng nhập bằng PIN. Trả về {ok, token, user} — không bao giờ trả PIN ra client.
 * Thông báo lỗi cố tình không phân biệt "không có PIN này" với "PIN bị khoá",
 * để không giúp người dò biết PIN nào tồn tại.
 */
function jpLoginPin(pin) {
  var p = jpChuanPin_(pin);
  if (!p) return { ok: false, msg: 'Nhập đúng ' + JP_PIN_LEN + ' số' };

  /* Chưa tài khoản nào có PIN ⇒ cấp PIN kế toán mặc định. Không có bước này thì
     ngay sau khi đổi sang đăng nhập PIN là không ai vào được, kể cả kế toán. */
  jpCapPinMacDinh_();

  var chk = null;
  var hit = jpRows_(JP_TABS.USERS).filter(function (r) {
    if (jpStr_(r.active) === 'N' || !jpStr_(r.pin)) return false;
    var c = jpCheckPass_(r.pin, p);
    if (c.ok) { chk = c; return true; }
    return false;
  })[0];

  if (!hit) {
    jpGhiNhanSai_();
    return { ok: false, msg: 'Mã PIN không đúng' };
  }

  /* Ai gõ PIN thẳng vào sheet (chưa băm) thì băm lại ngay tại chỗ */
  if (chk && chk.legacy) {
    jpFields_(JP_TABS.USERS, hit._row, { pin: jpMakePass_(p) });
    jpAudit_(hit, 'PIN_HASHED', '', jpStr_(hit.username), '');
  }

  var ra = jpMoPhien_(hit, p);

  /*
   * ⚠️ MÀN CHÍNH ĐI LUÔN TRONG LƯỢT ĐĂNG NHẬP (Andy 10/08/2026: *"bấm cái nào cũng
   * thấy load lâu quá"*). Cùng `jpBootstrap` gộp hai danh sách, cả màn chính của nhân
   * viên còn **MỘT đợt chờ** thay vì ba — từ ~1,35s xuống ~0,45s.
   *
   * ⚠️ `catch` im lặng ở đây là ĐÚNG, khác mọi chỗ khác trong repo: đây chỉ là bản
   * gói kèm cho nhanh, và client CÓ ĐƯỜNG LÙI — không thấy `boot` thì nó tự gọi
   * `jpBootstrap` và lỗi thật sẽ hiện ra ở đó. Để lỗi vỡ ra đây là **không đăng nhập
   * được** chỉ vì một tab danh mục đọc không ra.
   *
   * ⚠️ Không gói cho kế toán và không gói khi còn PIN mặc định — `jpBootstrap` tự lo
   * hai chuyện đó, đừng nhân đôi luật ở đây.
   */
  if (ra && ra.ok && !jpIsKtRole_(hit.role)) {
    try { ra.boot = jpBootstrap(ra.token); } catch (e) { ra.bootLoi = String(e && e.message); }
  }
  return ra;
}

/*
 * ĐÃ BỎ `jpLogin(username, password)`.
 *
 * Giao diện chuyển hết sang PIN, nhưng hàm server thì client nào cũng gọi được —
 * để lại là còn một cửa vào bằng mật khẩu cũ, mà mật khẩu cũ là `123456` ai cũng
 * biết. Cùng lý do, `jpCapPinMacDinh_` xoá luôn cột `password`.
 * Bỏ theo: `jpChangePassword`, `jpBamMatKhauCu` — không còn mật khẩu để đổi.
 */

/**
 * PIN mặc định do hệ thống tự cấp — ai cũng đoán ra, nên còn dùng số này thì coi
 * như CHƯA có mật khẩu. `222` là của kế toán, `101` là nhân viên mẫu của jpSetup_.
 */
var JP_PIN_MAC_DINH = ['222', '101'];

function jpLaPinMacDinh_(pin) {
  return JP_PIN_MAC_DINH.indexOf(jpChuanPin_(pin)) >= 0;
}

/** Cấp token + ghi phiên. `pinVuaGo` để biết có đang dùng PIN mặc định không. */
function jpMoPhien_(hit, pinVuaGo) {
  var token = Utilities.getUuid();
  var payload = {
    id: hit.id, username: hit.username, hoTen: hit.hoTen,
    role: hit.role, machineType: hit.machineType,
    locationIds: jpParseIds_(hit.locationIds),
    phaiDoiPin: jpLaPinMacDinh_(pinVuaGo),
    exp: Date.now() + JP_SESSION_HOURS * 3600 * 1000
  };
  CacheService.getScriptCache()
    .put('JPSES_' + token, JSON.stringify(payload), JP_CACHE_MAX_SEC);

  jpAudit_(hit, 'LOGIN', '', '', payload.phaiDoiPin ? { pinMacDinh: true } : '');
  return { ok: true, token: token, user: jpPublicUser_(payload) };
}

function jpLogout(token) {
  CacheService.getScriptCache().remove('JPSES_' + token);
  return { ok: true };
}

/*──────────────────── ③ PHIÊN ────────────────────*/

/**
 * Lấy user từ token. Ném lỗi nếu hết hạn — client bắt và về màn đăng nhập.
 *
 * CÒN DÙNG PIN MẶC ĐỊNH THÌ KHÔNG LÀM ĐƯỢC GÌ (chốt 04/08/2026).
 * PIN `222` nằm trong tài liệu, trong code, và trước bản vá hôm nay còn đọc được
 * từ ngoài internet qua `jpKhoiTao()`. Nhắc nhau đổi thì lúc bận sẽ quên, nên
 * chặn thẳng: đăng nhập được, nhưng mọi cửa đều đóng cho tới khi đổi PIN.
 *
 * Chặn ở ĐÂY chứ không ở `jpNeedKT_`/`jpNeedNV_`, vì có 9 hàm chỉ đi qua `jpAuth_`
 * (jpGetReport, jpUploadPhoto, jpPaymentHistory…) — chặn ở kia là còn 9 lối hở.
 *
 * `choPhepPinMacDinh` chỉ dành cho đúng ba đường phải sống để còn đổi được PIN:
 * `jpBootstrap` (dựng màn hình), `jpDoiPin` (đổi), `jpLogout` (thoát).
 */
function jpAuth_(token, choPhepPinMacDinh) {
  var key = 'JPSES_' + jpStr_(token);
  var cache = CacheService.getScriptCache();
  var raw = cache.get(key);
  if (!raw) throw new Error('SESSION_EXPIRED');
  var u = JSON.parse(raw);
  if (u.exp && u.exp < Date.now()) throw new Error('SESSION_EXPIRED');

  if (u.phaiDoiPin && !choPhepPinMacDinh) {
    throw new Error('PHAI_DOI_PIN: Tài khoản đang dùng PIN mặc định. ' +
                    'Bấm "Đổi PIN" và đặt số mới thì mới dùng được các chức năng.');
  }

  /* Gia hạn TTL cache: còn làm việc thì còn phiên, tới mốc u.exp mới hết */
  cache.put(key, raw, JP_CACHE_MAX_SEC);
  return u;
}

function jpPublicUser_(u) {
  return {
    id: u.id, hoTen: u.hoTen, role: u.role,
    machineType: u.machineType, locationIds: u.locationIds || [],
    phaiDoiPin: !!u.phaiDoiPin
  };
}

function jpParseIds_(v) {
  return jpStr_(v).split(/[,;|]/).map(function (s) { return s.trim(); })
                  .filter(function (s) { return !!s; });
}

/*──────────────────── ③b CẤP PIN LẦN ĐẦU ────────────────────*/

/** PIN kế toán mặc định (Andy chốt 04/08/2026). */
var JP_PIN_KETOAN_MACDINH = '222';

/**
 * Chạy một lần trong đời hệ thống: chưa tài khoản nào có PIN thì cấp PIN `222`
 * cho MỘT tài khoản kế toán, gộp hai vai trò kế toán cũ về `KETOAN`.
 *
 * CỐ Ý không tự cấp PIN cho nhân viên: PIN sinh tự động thì phải in ra đâu đó
 * mới dùng được, mà chỗ nào cũng là chỗ rò. Kế toán tự đặt PIN cho từng nhân
 * viên ở Cấu hình → Tài khoản, tự gõ nên tự biết.
 */
function jpCapPinMacDinh_() {
  var users = jpRows_(JP_TABS.USERS);
  if (!users.length) return null;
  if (users.some(function (r) { return !!jpStr_(r.pin); })) return null;   // đã có PIN

  var kt = users.filter(function (r) { return jpIsKtRole_(jpStr_(r.role)); });
  if (!kt.length) return null;

  /* Tài khoản kế toán đầu tiên thành KETOAN + PIN 222 */
  var chinh = kt[0];
  jpFields_(JP_TABS.USERS, chinh._row, {
    pin: jpMakePass_(JP_PIN_KETOAN_MACDINH),
    role: JP_ROLE_KT,
    note: jpStr_(chinh.note) + ' · Gộp về 1 tài khoản kế toán, PIN mặc định — đổi ngay'
  });

  /* Các tài khoản kế toán còn lại: ngưng dùng, không xoá để còn tra lịch sử */
  var ngung = [];
  kt.slice(1).forEach(function (r) {
    jpFields_(JP_TABS.USERS, r._row, {
      active: 'N',
      note: jpStr_(r.note) + ' · Ngưng: đã gộp về 1 tài khoản kế toán duy nhất'
    });
    ngung.push(jpStr_(r.username));
  });

  /* Xoá sạch cột password: không còn đường đăng nhập bằng mật khẩu, để lại chỉ
     là giữ một bí mật vô dụng trong sheet (và mật khẩu cũ là 123456). */
  users.forEach(function (r) {
    if (jpStr_(r.password)) jpFields_(JP_TABS.USERS, r._row, { password: '' });
  });

  jpAudit_(null, 'PIN_KHOI_TAO', '', jpStr_(chinh.username),
           { role: JP_ROLE_KT, ngungDung: ngung, xoaPassword: true });
  jpClearCache_(JP_TABS.USERS.name);
  return { chinh: jpStr_(chinh.username), ngung: ngung };
}

/*──────────────────── ④ QUYỀN ────────────────────*/

/*
 * ⚠️ Vai trò đọc qua `jpStr_` (có TRIM) chứ đừng so thẳng ô sheet. Kế toán gõ tay
 * `'NHANVIEN '` thừa một dấu cách là `===` trượt, và cả cơ sở bị chặn với đúng câu
 * "Chức năng này dành cho nhân viên" — nhìn vào sheet thấy chữ y hệt nên không ai
 * lần ra. Trim là hết cả một lớp lỗi im lặng.
 */
function jpVaiTro_(u) { return jpStr_(u && u.role); }

function jpIsNV_(u) { return jpVaiTro_(u) === JP_ROLE_NV; }

/** Nhận cả 2 vai trò kế toán cũ, để tài khoản đã có vẫn dùng được. */
function jpIsKtRole_(role) {
  var r = jpStr_(role);
  return r === JP_ROLE_KT || r === JP_ROLE_KT_DT || r === JP_ROLE_KT_KHO;
}
function jpIsKT_(u) { return jpIsKtRole_(jpVaiTro_(u)); }

/*
 * MỘT vai trò kế toán duy nhất (chốt 04/08/2026) ⇒ kế toán nào cũng làm được mọi
 * việc của kế toán. Hai hàm này giữ tên cũ để không phải sửa rải rác nơi gọi, và
 * để lần sau đọc còn hiểu vì sao chúng luôn trả true.
 */
function jpIsKtRev_(u) { return jpIsKT_(u); }
function jpIsKtSto_(u) { return jpIsKT_(u); }

/*
 * Hai câu chặn vai trò phải NÓI RA ĐANG LÀ AI và LÀM GÌ TIẾP.
 *
 * Cả hai web dùng CHUNG một cửa đăng nhập (`jpLoginPin`), nên PIN kế toán mở được
 * cả web nhân viên và ngược lại — vào được nhưng bấm gì cũng bị chặn. Câu cũ chỉ
 * ghi "Chức năng này dành cho nhân viên": đúng nhưng vô dụng, người đọc không biết
 * mình đang đăng nhập bằng PIN nào và phải làm gì. Andy đã mắc đúng chỗ này
 * 06/08/2026 — gõ PIN kế toán vào web nhân viên rồi bấm "Mở báo cáo".
 */
function jpTenVaiTro_(u) {
  var r = jpVaiTro_(u);
  return jpIsKtRole_(r) ? 'KẾ TOÁN' : r === JP_ROLE_NV ? 'NHÂN VIÊN' : (r || 'chưa đặt');
}

function jpNeedNV_(u) {
  if (!jpIsNV_(u)) {
    throw new Error('Màn này của NHÂN VIÊN CƠ SỞ, mà mã PIN vừa nhập là tài khoản ' +
      jpTenVaiTro_(u) + ' (' + (jpStr_(u && u.hoTen) || '—') + '). ' +
      'Thoát ra rồi đăng nhập lại bằng mã PIN của cơ sở. ' +
      'Quên PIN cơ sở thì vào web kế toán → Cấu hình → Tài khoản → Cấp lại.');
  }
  return u;
}
function jpNeedKT_(u) {
  if (!jpIsKT_(u)) {
    throw new Error('Màn này của KẾ TOÁN, mà mã PIN vừa nhập là tài khoản ' +
      jpTenVaiTro_(u) + ' (' + (jpStr_(u && u.hoTen) || '—') + '). ' +
      'Thoát ra rồi đăng nhập lại bằng mã PIN kế toán.');
  }
  return u;
}

/** Nhân viên chỉ được đụng cơ sở đã gán. Kế toán xem tất cả. */
function jpCanSeeLoc_(u, locationId) {
  if (jpIsKT_(u)) return true;
  return (u.locationIds || []).indexOf(String(locationId)) >= 0;
}

function jpNeedLoc_(u, locationId) {
  if (!jpCanSeeLoc_(u, locationId)) throw new Error('Không có quyền với cơ sở này');
}

/**
 * Loại máy nhân viên được gán. Rỗng = được cả hai (dùng cho người kiêm cả hai loại).
 * Kế toán không bị chặn — họ xem mọi báo cáo.
 */
function jpMayTypeCua_(u) {
  if (jpIsKtRole_(u && u.role)) return '';
  var t = jpStr_(u && u.machineType).toUpperCase();
  return (t === JP_TYPE_COIN || t === JP_TYPE_MONEY) ? t : '';
}

/**
 * Chặn nhân viên mở loại báo cáo không phải của mình.
 * Andy chốt 04/08/2026: *"khi anh phân quyền thì đúng mẫu nào hiện mẫu đó thôi"*.
 *
 * ⚠️ Chặn ở ĐÂY chứ không chỉ ẩn ô chọn trên giao diện. Web ở `ANYONE_ANONYMOUS`
 * nên ai cũng gọi `google.script.run.jpOpenReport(token, loc, ..., 'XU')` từ console
 * được — ẩn giao diện là chặn hờ, không phải chặn.
 */
function jpNeedMayType_(u, mType) {
  var duoc = jpMayTypeCua_(u);
  if (!duoc) return;                                  // gán rỗng = cả hai loại
  if (jpStr_(mType).toUpperCase() !== duoc) {
    throw new Error('Tài khoản này được gán ' +
      (duoc === JP_TYPE_COIN ? 'MÁY XU' : 'MÁY TIỀN') +
      ' — không mở được báo cáo ' +
      (jpStr_(mType).toUpperCase() === JP_TYPE_COIN ? 'MÁY XU' : 'MÁY TIỀN') +
      '. Kế toán đổi loại máy trong Cấu hình → Tài khoản nếu cần.');
  }
}

/*──────────────────── ⑤ ĐỔI PIN / MẬT KHẨU ────────────────────*/

/** Tự đổi PIN của mình. PIN mới phải chưa ai dùng, vì đăng nhập chỉ bằng PIN. */
function jpDoiPin(token, pinCu, pinMoi) {
  var u = jpAuth_(token, true);            // phải qua được kể cả khi PIN mặc định
  var moi = jpChuanPin_(pinMoi);
  if (!moi) return { ok: false, msg: 'PIN mới phải đúng ' + JP_PIN_LEN + ' số' };
  if (moi === jpChuanPin_(pinCu)) return { ok: false, msg: 'PIN mới phải khác PIN cũ' };

  var row = jpFindOne_(JP_TABS.USERS, 'id', u.id);
  if (!row) return { ok: false, msg: 'Không tìm thấy tài khoản' };
  if (!jpStr_(row.pin) || !jpCheckPass_(row.pin, pinCu).ok) {
    jpGhiNhanSai_();
    return { ok: false, msg: 'PIN hiện tại không đúng' };
  }

  var trung = jpPinDaDung_(moi, u.id);
  if (trung) return { ok: false, msg: 'PIN này đã có người dùng, chọn số khác' };

  jpFields_(JP_TABS.USERS, row._row, { pin: jpMakePass_(moi) });

  /* Gỡ cờ trên PHIÊN ĐANG DÙNG — không thì đổi xong vẫn bị chặn tới lúc hết phiên,
     mà PIN mới thì không còn là mặc định nữa. */
  var key = 'JPSES_' + jpStr_(token);
  var cache = CacheService.getScriptCache();
  var raw = cache.get(key);
  if (raw) {
    var ses = JSON.parse(raw);
    ses.phaiDoiPin = jpLaPinMacDinh_(moi);
    cache.put(key, JSON.stringify(ses), JP_CACHE_MAX_SEC);
  }

  jpAudit_(u, 'DOI_PIN', '', u.id, '');
  return { ok: true, msg: 'Đã đổi PIN' };
}


/*──────────────────── ⑥ KHỞI TẠO LẦN ĐẦU ────────────────────*/

/**
 * Chạy tay 1 lần từ trình chỉnh sửa: tạo đủ tab + 3 tài khoản mẫu.
 * An toàn khi chạy lại — không ghi đè dữ liệu đã có.
 */
function jpSetup_() {
  Object.keys(JP_TABS).forEach(function (k) { jpSheet_(JP_TABS[k]); });

  var users = jpRows_(JP_TABS.USERS);
  if (!users.length) {
    /* Một tài khoản kế toán duy nhất (PIN 222) + một nhân viên mẫu (PIN 101) */
    [['ketoan', JP_PIN_KETOAN_MACDINH, 'Kế toán', JP_ROLE_KT, ''],
     ['nhanvien', '101', 'Nhân viên mẫu', JP_ROLE_NV, JP_TYPE_MONEY]
    ].forEach(function (a, i) {
      jpAppend_(JP_TABS.USERS, {
        id: 'U' + ('00' + (i + 1)).slice(-3),
        username: a[0], pin: jpMakePass_(a[1]), password: '',
        hoTen: a[2], role: a[3],
        machineType: a[4], locationIds: '', active: 'Y', createdAt: new Date(),
        note: 'Tạo tự động — đổi PIN ngay'
      });
    });
  }
  return 'Đã tạo ' + Object.keys(JP_TABS).length + ' tab. Kế toán PIN ' +
         JP_PIN_KETOAN_MACDINH + ', nhân viên mẫu PIN 101 — đổi ngay.';
}

/*──────────────────── ⑦ BĂM LẠI MẬT KHẨU CŨ ────────────────────*/



