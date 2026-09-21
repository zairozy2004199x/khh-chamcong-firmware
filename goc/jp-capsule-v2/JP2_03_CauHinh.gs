/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB NHÂN VIÊN  ·  03_CauHinh
 * ---------------------------------------------------------------------------
 * Danh mục do KẾ TOÁN quản lý: tài khoản NV · cơ sở · cụm máy · ô/máy · hàng.
 *
 * Andy chốt: ở màn cấu hình tài khoản, thứ duy nhất chỉnh thường xuyên là
 * LOẠI MÁY (TIỀN / XU). Quan hệ NV–cơ sở làm nhiều–nhiều ngay từ đầu.
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① NẠP CẤU HÌNH CHO CLIENT ────────────────────*/

/** Gói dữ liệu nền cho web nhân viên khi mở app. */
function jpBootstrap(token) {
  var u = jpAuth_(token, true);            // phải dựng được màn hình để còn đổi PIN
  var locs = jpRows_(JP_TABS.LOCATIONS).filter(function (l) {
    return jpStr_(l.active) !== 'N' && jpCanSeeLoc_(u, l.id);
  });
  var locIds = locs.map(function (l) { return String(l.id); });

  var ra = {
    ok: true,
    user: jpPublicUser_(u),
    locations: locs.map(jpPubLoc_),
    clusters: jpRows_(JP_TABS.CLUSTERS).filter(function (c) {
      return jpStr_(c.active) !== 'N' && locIds.indexOf(String(c.locationId)) >= 0;
    }).map(jpPubCluster_),
    machines: jpRows_(JP_TABS.MACHINES).filter(function (m) {
      return jpStr_(m.active) !== 'N' && locIds.indexOf(String(m.locationId)) >= 0;
    }).map(jpPubMachine_),
    items: jpRows_(JP_TABS.ITEMS).filter(function (i) {
      return jpStr_(i.active) !== 'N';
    }).map(jpPubItem_),
    rates: {
      moneyPulse: JP_RATE_MONEY_PULSE,
      coin: JP_RATE_COIN,
      coinMoney: JP_RATE_COIN_MONEY
    }
  };

  /*
   * ⚠️ HAI DANH SÁCH CỦA MÀN CHÍNH ĐI CÙNG LƯỢT NÀY — đừng tách ra thành hai lượt gọi
   * riêng nữa (Andy 10/08/2026: *"bấm cái nào cũng thấy load lâu quá"*).
   *
   * Trên Apps Script mỗi lượt `google.script.run` tốn ~0,45s độ trễ TRƯỚC khi tính tới
   * số lượt Sheets. Bản trước màn chính của nhân viên là BA đợt nối tiếp:
   * `jpLoginPin` → `jpBootstrap` → (`jpMyReports` + `jpMyUnpaid`) ≈ **1,35s** chỉ để
   * thấy màn hình đầu tiên — thứ nhân viên gặp mỗi ca.
   *
   * Gộp vào đây RẺ HƠN gọi riêng, không chỉ đỡ một đợt: `jpRows_` cache trong một lần
   * chạy, nên hai hàm cùng đọc `JP_Reports` chỉ tốn MỘT lượt đọc thay vì hai.
   *
   * ⚠️ Chỉ cho NHÂN VIÊN. Hai hàm kia dùng `jpNeedNV_` nên vai trò kế toán là **throw**
   * — mà web kế toán cũng gọi `jpBootstrap` (lúc khôi phục phiên).
   *
   * ⚠️ Còn PIN mặc định thì BỎ QUA: `jpMyReports` đi qua `jpAuth_(token)` (không có cờ
   * nới), tức nó throw đúng như thiết kế. `jpBootstrap` thì phải sống để còn đổi được
   * PIN — nên ở đây không được để nó chết theo.
   */
  if (!jpIsKtRole_(u.role) && !u.phaiDoiPin) {
    ra.myReports = jpMyReports(token, 30);
    ra.unpaid = jpMyUnpaid(token);
  }
  return ra;
}

function jpPubLoc_(l) {
  return {
    id: String(l.id), code: jpStr_(l.code), name: jpStr_(l.name),
    maKH: jpStr_(l.maKH), machineType: jpStr_(l.machineType) || JP_TYPE_MONEY,
    photoDefault: jpNum_(l.photoDefault) || 1,
    bcMau: jpBcMau_(l.bcMau),
    coDhTrung: jpCoDhTrung_(l.coDhTrung),
    chonGiaXung: jpChonGiaXung_(l.chonGiaXung),
    maDinhDanh: jpStr_(l.maDinhDanh)
  };
}
function jpPubCluster_(c) {
  return {
    id: String(c.id), locationId: String(c.locationId), name: jpStr_(c.name),
    payboxSerial: jpStr_(c.payboxSerial), hasQR: jpStr_(c.hasQR) === 'Y',
    /* Cụm: trống ⇒ 1 nếu có QR, 0 nếu chưa lắp — khớp với lúc lưu */
    photoCount: jpSoAnh_(c.photoCount, jpStr_(c.hasQR) === 'Y' ? 1 : 0)
  };
}
function jpPubMachine_(m) {
  return {
    id: String(m.id), locationId: String(m.locationId),
    clusterId: String(m.clusterId), code: jpStr_(m.code),
    itemCode: jpStr_(m.itemCode), itemMisa: jpStr_(m.itemMisa),
    photoCount: jpSoAnh_(m.photoCount)
  };
}
function jpPubItem_(i) {
  var code = jpStr_(i.code);
  return {
    code: code, misa: jpStr_(i.misa), name: jpStr_(i.name),
    price: jpNum_(i.price) || jpGiaTuMa_(code),
    /* Mã đời cũ chưa có `dvt` thì rơi về 'Quả' — xem `jpDvt_` ở 00_Config, đó là
       nguồn duy nhất của phép rơi-về này. */
    dvt: jpDvt_(i)
  };
}

/*──────────────────── ② TÀI KHOẢN NHÂN VIÊN ────────────────────*/

function jpCfgListUsers(token) {
  jpNeedKT_(jpAuth_(token));
  return jpRows_(JP_TABS.USERS).map(function (r) {
    return {
      id: String(r.id), username: jpStr_(r.username), hoTen: jpStr_(r.hoTen),
      role: jpStr_(r.role), machineType: jpStr_(r.machineType),
      locationIds: jpParseIds_(r.locationIds),
      /* Chỉ nói CÓ hay KHÔNG có PIN. PIN đã băm, và cũng không được trả ra client. */
      coPin: !!jpStr_(r.pin),
      active: jpStr_(r.active) !== 'N', note: jpStr_(r.note)
    };
  });
}

/**
 * Lưu tài khoản. p = {id?, username, password?, hoTen, role, machineType,
 *                     locationIds:[], active, note}
 */
function jpCfgSaveUser(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  if (!jpStr_(p.username)) throw new Error('Thiếu tên đăng nhập');
  if (!jpStr_(p.hoTen)) throw new Error('Thiếu họ tên');

  var o = {
    username: jpStr_(p.username).toLowerCase(),
    hoTen: jpStr_(p.hoTen),
    role: jpStr_(p.role) || JP_ROLE_NV,
    machineType: jpStr_(p.machineType),
    locationIds: (p.locationIds || []).join(','),
    active: p.active === false ? 'N' : 'Y',
    note: jpStr_(p.note)
  };

  var pass = jpStr_(p.password);
  if (pass && pass.length < 6) throw new Error('Mật khẩu tối thiểu 6 ký tự');

  /* PIN: đăng nhập chỉ bằng PIN nên PIN phải DUY NHẤT toàn hệ thống */
  var pinRaw = jpStr_(p.pin);
  var pin = jpChuanPin_(pinRaw);
  if (pinRaw && !pin) throw new Error('Mã PIN phải đúng ' + JP_PIN_LEN + ' chữ số');

  var ex = p.id ? jpFindOne_(JP_TABS.USERS, 'id', p.id) : null;

  if (pin) {
    var trung = jpPinDaDung_(pin, ex ? ex.id : '');
    if (trung) {
      throw new Error('PIN ' + pin + ' đã cấp cho ' +
                      (jpStr_(trung.hoTen) || jpStr_(trung.username)) + ' — chọn số khác');
    }
  }

  if (ex) {
    if (pass) o.password = jpMakePass_(pass);
    if (pin) o.pin = jpMakePass_(pin);
    jpUpdate_(JP_TABS.USERS, ex._row, o);
    /* Audit KHÔNG được chứa mật khẩu hay PIN — kể cả bản đã băm */
    jpAudit_(u, 'CFG_USER_UPDATE', '', o.username,
             jpUserAuditSafe_(o, !!pass, !!pin));
    return { ok: true, id: String(ex.id) };
  }

  var dup = jpRows_(JP_TABS.USERS).filter(function (r) {
    return jpStr_(r.username).toLowerCase() === o.username;
  });
  if (dup.length) throw new Error('Tên đăng nhập đã tồn tại');

  /* Tài khoản mới BẮT BUỘC có PIN, không thì tạo ra rồi không ai đăng nhập được */
  if (!pin) throw new Error('Nhập mã PIN ' + JP_PIN_LEN + ' số cho tài khoản mới');

  o.id = jpNextId_('U');
  o.pin = jpMakePass_(pin);
  o.password = pass ? jpMakePass_(pass) : '';
  o.createdAt = new Date();
  jpAppend_(JP_TABS.USERS, o);
  jpAudit_(u, 'CFG_USER_ADD', '', o.username, { role: o.role });
  return { ok: true, id: o.id };
}

/** Bản sao dùng để ghi audit: bỏ hẳn password và pin, chỉ ghi có đổi hay không. */
function jpUserAuditSafe_(o, passChanged, pinChanged) {
  var safe = {};
  Object.keys(o).forEach(function (k) {
    if (k !== 'password' && k !== 'pin') safe[k] = o[k];
  });
  safe.passwordChanged = !!passChanged;
  safe.pinChanged = !!pinChanged;
  return safe;
}

/*──────────────────── ③ CƠ SỞ ────────────────────*/

function jpCfgListLocations(token) {
  jpNeedKT_(jpAuth_(token));
  /* Mã định danh khai ở bảng chung của công ty — để màn Cấu hình đừng báo "chưa khai"
     cho cơ sở thật ra đã có mã và đối soát đang chạy đúng. Xem `jpDinhDanhChung_`. */
  var ddChung = jpDinhDanhChung_();
  return jpRows_(JP_TABS.LOCATIONS).map(function (l) {
    var o = jpPubLoc_(l);
    o.active = jpStr_(l.active) !== 'N';
    o.note = jpStr_(l.note);
    o.ddChung = ddChung[o.id] || [];
    return o;
  });
}

/** p = {id?, code, name, maKH, machineType, photoDefault, bcMau, active, note} */
function jpCfgSaveLocation(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  if (!jpStr_(p.name)) throw new Error('Thiếu tên cơ sở');

  var o = {
    code: jpStr_(p.code), name: jpStr_(p.name),
    maKH: jpStr_(p.maKH),                       // ← mã KH gán ở CẤP CƠ SỞ
    machineType: jpStr_(p.machineType) || JP_TYPE_MONEY,
    photoDefault: jpNum_(p.photoDefault) || 1,
    /* Mẫu báo cáo nhân viên. Đổi ở đây CHỈ ăn vào báo cáo TẠO SAU ĐÓ — báo cáo đang
       mở giữ mẫu đã chốt lúc tạo (`JP_Reports.bcMau`), không thì nhân viên đang nhập
       dở bị đổi bố cục bảng giữa buổi và số đã gõ rơi vào cột khác. */
    bcMau: jpBcMau_(p.bcMau),
    /* Đồng hồ đếm trứng. KHÁC `bcMau` ngay chỗ này: đổi cờ này ăn NGAY vào cả báo cáo
       đang nhập dở (Andy chốt 15/08/2026). Được, vì nó chỉ ẩn hai ô chứ không xáo lại
       cách tính — khác `bcMau` là đổi hẳn bố cục bảng và số đã gõ sẽ rơi cột khác. */
    coDhTrung: p.coDhTrung === false ? 'N' : 'Y',
    /* HAI LOẠI MÁY TIỀN lẫn nhau — mặc định TẮT, ngược chiều `coDhTrung`. Xem
       `jpChonGiaXung_`: 12 cơ sở không cần thì đừng cho thấy ô chọn. */
    chonGiaXung: p.chonGiaXung === true ? 'Y' : 'N',
    /* Mã định danh nộp tiền — bỏ dấu cách cho khớp nội dung CK đã chuẩn hoá. */
    maDinhDanh: jpStr_(p.maDinhDanh).replace(/\s+/g, '').toUpperCase(),
    active: p.active === false ? 'N' : 'Y',
    note: jpStr_(p.note)
  };

  var ex = p.id ? jpFindOne_(JP_TABS.LOCATIONS, 'id', p.id) : null;
  if (ex) {
    jpUpdate_(JP_TABS.LOCATIONS, ex._row, o);
    jpAudit_(u, 'CFG_LOC_UPDATE', '', o.name, o);
    return { ok: true, id: String(ex.id) };
  }
  o.id = jpNextId_('L');
  jpAppend_(JP_TABS.LOCATIONS, o);
  jpAudit_(u, 'CFG_LOC_ADD', '', o.name, o);
  return { ok: true, id: o.id };
}

/*──────────────────── ④ CỤM MÁY (ảnh Pay Box gán ở đây) ────────────────────*/

function jpCfgListClusters(token, locationId) {
  jpNeedKT_(jpAuth_(token));
  var all = jpRows_(JP_TABS.CLUSTERS);
  if (locationId) {
    all = all.filter(function (c) { return String(c.locationId) === String(locationId); });
  }
  return all.map(function (c) {
    var o = jpPubCluster_(c);
    o.active = jpStr_(c.active) !== 'N';
    o.note = jpStr_(c.note);
    return o;
  });
}

/** p = {id?, locationId, name, payboxSerial, hasQR, photoCount, active, note} */
function jpCfgSaveCluster(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  if (!jpStr_(p.locationId)) throw new Error('Thiếu cơ sở');

  var hasQR = (p.hasQR === true || jpStr_(p.hasQR) === 'Y');
  var o = {
    locationId: jpStr_(p.locationId),
    name: jpStr_(p.name) || 'Cụm',
    payboxSerial: jpStr_(p.payboxSerial),
    hasQR: hasQR ? 'Y' : 'N',
    // Cụm chưa lắp QR ⇒ không yêu cầu ảnh Pay Box
    photoCount: jpSoAnh_(p.photoCount, hasQR ? 1 : 0),
    active: p.active === false ? 'N' : 'Y',
    note: jpStr_(p.note)
  };

  var ex = p.id ? jpFindOne_(JP_TABS.CLUSTERS, 'id', p.id) : null;
  if (ex) {
    jpUpdate_(JP_TABS.CLUSTERS, ex._row, o);
    jpAudit_(u, 'CFG_CLUSTER_UPDATE', '', o.name, o);
    return { ok: true, id: String(ex.id) };
  }
  o.id = jpNextId_('C');
  jpAppend_(JP_TABS.CLUSTERS, o);
  jpAudit_(u, 'CFG_CLUSTER_ADD', '', o.name, o);
  return { ok: true, id: o.id };
}

/*──────────────────── ⑤ Ô / MÁY ────────────────────*/

function jpCfgListMachines(token, locationId) {
  jpNeedKT_(jpAuth_(token));
  var all = jpRows_(JP_TABS.MACHINES);
  if (locationId) {
    all = all.filter(function (m) { return String(m.locationId) === String(locationId); });
  }
  return all.map(function (m) {
    var o = jpPubMachine_(m);
    o.active = jpStr_(m.active) !== 'N';
    o.note = jpStr_(m.note);
    return o;
  });
}

/** p = {id?, locationId, clusterId, code, itemCode, itemMisa, photoCount, active, note} */
function jpCfgSaveMachine(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  if (!jpStr_(p.locationId)) throw new Error('Thiếu cơ sở');

  var o = {
    locationId: jpStr_(p.locationId),
    clusterId: jpStr_(p.clusterId),
    code: jpStr_(p.code),
    itemCode: jpStr_(p.itemCode),
    itemMisa: jpStr_(p.itemMisa),
    photoCount: jpSoAnh_(p.photoCount),
    active: p.active === false ? 'N' : 'Y',
    note: jpStr_(p.note)
  };

  var ex = p.id ? jpFindOne_(JP_TABS.MACHINES, 'id', p.id) : null;
  if (ex) {
    jpUpdate_(JP_TABS.MACHINES, ex._row, o);
    jpAudit_(u, 'CFG_MACHINE_UPDATE', '', o.code, o);
    return { ok: true, id: String(ex.id) };
  }
  o.id = jpNextId_('M');
  jpAppend_(JP_TABS.MACHINES, o);
  jpAudit_(u, 'CFG_MACHINE_ADD', '', o.code, o);
  return { ok: true, id: o.id };
}

/*──────────────────── ⑥ DANH MỤC HÀNG ────────────────────*/

function jpCfgListItems(token) {
  jpNeedKT_(jpAuth_(token));
  return jpRows_(JP_TABS.ITEMS).map(function (i) {
    var o = jpPubItem_(i);
    o.active = jpStr_(i.active) !== 'N';
    o.note = jpStr_(i.note);
    o.priceAuto = jpGiaTuMa_(o.code);
    return o;
  });
}

/**
 * p = {code, misa, name, price?, active, note}
 * Bỏ trống price ⇒ hệ thống lấy giá theo tiền tố mã (50JP/100JP/150JP/200JP).
 */
function jpCfgSaveItem(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var code = jpStr_(p.code);
  if (!code) throw new Error('Thiếu mã hàng');

  var o = {
    code: code, misa: jpStr_(p.misa), name: jpStr_(p.name),
    price: jpNum_(p.price) || jpGiaTuMa_(code),
    active: p.active === false ? 'N' : 'Y',
    note: jpStr_(p.note)
  };
  if (!o.price) throw new Error('Mã "' + code + '" không suy được giá — nhập giá tay');

  var ex = jpFindOne_(JP_TABS.ITEMS, 'code', code);
  if (ex) {
    jpUpdate_(JP_TABS.ITEMS, ex._row, o);
    jpAudit_(u, 'CFG_ITEM_UPDATE', '', code, o);
  } else {
    jpAppend_(JP_TABS.ITEMS, o);
    jpAudit_(u, 'CFG_ITEM_ADD', '', code, o);
  }
  return { ok: true, code: code };
}

/** Nhập hàng loạt danh mục hàng: rows = [{code,misa,name,price?}] */
function jpCfgImportItems(token, rows) {
  var u = jpNeedKT_(jpAuth_(token));
  rows = rows || [];
  var existing = {}, added = 0, updated = 0, failed = [];
  jpRows_(JP_TABS.ITEMS).forEach(function (r) { existing[jpStr_(r.code)] = r; });

  var toAppend = [], khongDoi = 0;
  rows.forEach(function (r) {
    var code = jpStr_(r.code);
    if (!code) return;
    var price = jpNum_(r.price) || jpGiaTuMa_(code);
    if (!price) { failed.push(code); return; }
    var o = {
      code: code, misa: jpStr_(r.misa), name: jpStr_(r.name),
      price: price, dvt: jpDvt_(r), active: 'Y', note: ''
    };
    var cu = existing[code];
    if (!cu) { toAppend.push(o); added++; return; }

    /* KHÔNG ghi khi không có gì đổi.
       `jpUpdate_` là 2 lượt gọi sheet mỗi dòng (đọc rồi ghi). Nhập lại đúng file cũ —
       việc kế toán làm thường xuyên, nhất là file danh mục 101 mã — mà ghi lại cả 101
       dòng là ~200 lượt gọi, chậm thấy được và không đổi lấy gì. So trước rồi mới ghi
       thì lần nhập lại tốn 0 lượt ghi.
       So bằng `jpStr_`/`jpNum_` chứ đừng so `!==` thô: ô sheet trả về số 100000 còn
       file CSV cho chuỗi '100000', so thô là lúc nào cũng thấy "khác". */
    var coDoi = false;
    Object.keys(o).forEach(function (k) {
      var moi = o[k], nay = cu[k];
      var giong = (k === 'price') ? jpNum_(moi) === jpNum_(nay)
                                  : jpStr_(moi) === jpStr_(nay);
      if (!giong) coDoi = true;
    });
    if (!coDoi) { khongDoi++; return; }
    jpUpdate_(JP_TABS.ITEMS, cu._row, o);
    updated++;
  });

  // Ghi 1 lần thay vì appendRow trong vòng lặp
  jpAppendMany_(JP_TABS.ITEMS, toAppend);

  jpAudit_(u, 'CFG_ITEM_IMPORT', '', '',
           { added: added, updated: updated, khongDoi: khongDoi, failed: failed.length });
  return { ok: true, added: added, updated: updated, khongDoi: khongDoi, failed: failed };
}


/*════════════════════ ⑧ CẤP TÀI KHOẢN + MÃ PIN THEO CƠ SỞ ════════════════════*/

/**
 * Tên đăng nhập quy ước của tài khoản cơ sở: `cs<mã cơ sở>`, bỏ hết ký tự lạ.
 *
 * ⚠️ **NGUỒN DUY NHẤT của quy ước này.** Hai chỗ dùng: `jpTaoPinCoSo` (lúc CẤP) và
 * `jpPinTheoCoSo` (lúc ĐỌC để dựng bảng cấu hình). Viết lại rời ở chỗ thứ hai là có
 * ngày hai bên hiểu khác nhau — lúc đó bảng báo "chưa có PIN" cho cơ sở đang có tài
 * khoản chạy ngon, rồi kế toán bấm Cấp và tạo ra tài khoản thứ hai cho cùng cơ sở.
 *
 * Khớp theo tên này chứ KHÔNG theo tên hiển thị: kế toán sửa tên cơ sở là mất dấu.
 */
function jpUserCoSo_(code) {
  return ('cs' + jpStr_(code)).toLowerCase().replace(/[^a-z0-9]/g, '');
}

/**
 * MỖI CƠ SỞ MỘT TÀI KHOẢN + MỘT MÃ PIN (Andy chốt 04/08/2026: *"em add và dựng mã
 * pin cơ sở luôn đi"*).
 *
 * Trước bản này `CLAUDE.md` ghi **"không tự cấp PIN cho nhân viên"**, lý do: PIN sinh
 * tự động thì phải in ra đâu đó mới dùng được, mà chỗ nào cũng là chỗ rò. Andy yêu
 * cầu cấp, nên đổi quyết định — nhưng giữ nguyên cái lo đó bằng cách:
 *
 *   · PIN **sinh ngẫu nhiên** tại thời điểm chạy, KHÔNG nằm trong repo, không đoán
 *     được theo thứ tự cơ sở.
 *   · Vào sheet chỉ có **bản băm** (`jpMakePass_`), y như mọi PIN khác.
 *   · Bản rõ trả về **ĐÚNG MỘT LẦN** trong kết quả hàm này cho kế toán ghi lại. Không
 *     ghi vào sheet, không vào `jpAudit_`. Mất thì cấp lại, không tra lại được.
 *
 * Vì sao một PIN cho cả cơ sở chứ không phải từng người: đây là ca trực bán hàng ở
 * mall, người trực đổi liên tục. Hệ quả đã biết và Andy đã chấp nhận: **báo cáo thuộc
 * về "tài khoản cơ sở"** nên `jpNeedOwner_` không tách được ai trong ca đã nhập —
 * muốn tách thì cấp PIN theo người.
 *
 * `loaiMay` của tài khoản lấy theo cơ sở, nên `jpNeedMayType_` tự khoá đúng mẫu: cơ
 * sở máy tiền thì chỉ mở được mẫu tiền. Cơ sở chưa rõ loại thì để trống = kiêm cả hai.
 *
 * @param {string} token  phiên kế toán
 * @param {Object} p      `{ locationIds?: [], capLai?: bool }`
 *                        `locationIds` trống = mọi cơ sở đang hoạt động.
 *                        `capLai = true` thì cấp PIN MỚI cho cơ sở đã có tài khoản.
 */
function jpTaoPinCoSo(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var chon = {};
  (p.locationIds || []).forEach(function (id) { chon[String(id)] = 1; });
  var capLai = p.capLai === true;

  var locs = jpRows_(JP_TABS.LOCATIONS).filter(function (l) {
    if (jpStr_(l.active) === 'N') return false;
    return Object.keys(chon).length ? chon[String(l.id)] : true;
  });
  if (!locs.length) throw new Error('Không có cơ sở nào đang hoạt động để cấp PIN');

  return jpLock_(function () {
    var users = jpRows_(JP_TABS.USERS);

    /* Tập PIN ĐÃ DÙNG, đọc một lần. `jpPinDaDung_` phải băm lại từng dòng nên gọi nó
       trong vòng lặp là O(số cơ sở × số người) lần băm — với 13 cơ sở thì chậm thấy
       được. Ở đây tự dò một lượt rồi giữ trong bộ nhớ. */
    var daDung = users.filter(function (r) { return jpStr_(r.pin); });
    var trung = function (pin) {
      /* PIN mặc định (`222`, `101`) coi như đã dùng — cấp trúng là tài khoản đó bị
         `jpAuth_` khoá cho tới khi đổi PIN. */
      if (jpLaPinMacDinh_(pin)) return true;
      for (var i = 0; i < daDung.length; i++) {
        if (jpCheckPass_(daDung[i].pin, pin).ok) return true;
      }
      return false;
    };

    /* Không gian PIN chỉ có 1000 số. Dò ngẫu nhiên rồi mới quét tuần tự để chắc chắn
       kết thúc — hết số thật thì phải NÓI RA, không được lặng lẽ bỏ qua cơ sở. */
    var soMoi = function () {
      var max = Math.pow(10, JP_PIN_LEN);
      for (var t = 0; t < 400; t++) {
        var pin = String(Math.floor(Math.random() * max));
        while (pin.length < JP_PIN_LEN) pin = '0' + pin;
        if (!trung(pin)) return pin;
      }
      for (var n = 0; n < max; n++) {
        var s = String(n);
        while (s.length < JP_PIN_LEN) s = '0' + s;
        if (!trung(s)) return s;
      }
      return '';
    };

    var them = [], doiPin = [], boQua = [], ketQua = [], hetSo = [];

    locs.forEach(function (l) {
      var lid = String(l.id);
      var code = jpStr_(l.code) || lid;
      var loaiMay = jpStr_(l.machineType);

      /* Tài khoản của cơ sở này: khớp theo `username` quy ước (`jpUserCoSo_` là nguồn
         duy nhất của quy ước đó), không khớp theo tên hiển thị. */
      var uname = jpUserCoSo_(code);
      var ex = users.filter(function (r) {
        return jpStr_(r.username).toLowerCase() === uname;
      })[0];

      var dong = {
        username: uname,
        hoTen: jpStr_(l.name) || code,
        role: JP_ROLE_NV,
        machineType: loaiMay,
        locationIds: lid,
        active: 'Y'
      };

      if (ex && !capLai) {
        /* Đã có tài khoản mà không xin cấp lại: vẫn đồng bộ cơ sở + loại máy (đây mới
           là chỗ hay lệch), nhưng KHÔNG đụng PIN — đổi PIN của cơ sở đang chạy mà
           không ai yêu cầu là làm họ không đăng nhập được giữa ca. */
        jpFields_(JP_TABS.USERS, ex._row,
                  { machineType: loaiMay, locationIds: lid, active: 'Y' });
        boQua.push(code);
        return;
      }

      var pin = soMoi();
      if (!pin) { hetSo.push(code); return; }
      var bam = jpMakePass_(pin);

      if (ex) {
        jpFields_(JP_TABS.USERS, ex._row,
                  { pin: bam, machineType: loaiMay, locationIds: lid, active: 'Y' });
        doiPin.push(code);
      } else {
        dong.id = jpNextId_('U');
        dong.pin = bam;
        dong.createdAt = new Date();
        dong.note = 'Tài khoản cơ sở — cấp tự động';
        jpAppend_(JP_TABS.USERS, dong);
        them.push(code);
      }

      /* Giữ bản băm trong danh sách đã dùng để PIN sau không trùng PIN vừa cấp */
      daDung.push({ pin: bam });
      ketQua.push({ code: code, tenCoSo: jpStr_(l.name), username: uname,
                    loaiMay: loaiMay || '(kiêm cả hai)', pin: pin });
    });

    /* Audit: SỐ LƯỢNG thôi. Không được có trường `pin` ở đây, kể cả bản băm. */
    jpAudit_(u, 'CFG_PIN_CO_SO', '', '',
             { them: them.length, doiPin: doiPin.length, boQua: boQua.length,
               hetSo: hetSo.length, capLai: capLai });

    var msg = 'Đã cấp ' + ketQua.length + ' mã PIN cơ sở' +
      (them.length ? ' · tạo mới ' + them.length : '') +
      (doiPin.length ? ' · đổi PIN ' + doiPin.length : '') +
      (boQua.length ? ' · giữ nguyên PIN cũ ' + boQua.length + ' (' + boQua.join(', ') +
                      ') — tick "Cấp lại PIN" nếu muốn đổi' : '') +
      (hetSo.length ? '\n⚠ HẾT SỐ PIN cho: ' + hetSo.join(', ') +
                      ' — chỉ có ' + Math.pow(10, JP_PIN_LEN) + ' số ' + JP_PIN_LEN +
                      ' chữ số, hãy ngưng tài khoản không dùng nữa' : '');

    return { ok: true, rows: ketQua, them: them, doiPin: doiPin, boQua: boQua,
             hetSo: hetSo, msg: msg,
             canhBao: 'Danh sách PIN chỉ hiện MỘT LẦN này — sheet chỉ lưu bản băm. ' +
                      'Ghi lại hoặc tải CSV trước khi rời màn hình.' };
  });
}

/*════════════════ ⑧b BẢNG CẤU HÌNH PIN THEO CƠ SỞ (chỉ đọc) ════════════════*/

/**
 * MỘT DÒNG MỘT CƠ SỞ — trạng thái tài khoản + PIN của từng điểm bán.
 *
 * Andy hỏi 06/08/2026: *"Với kế toán có bảng cấu hình pin mã cơ sở chưa em"*. Lúc đó
 * chỉ có **card CẤP** (hai nút, làm một lượt cho mọi cơ sở) và một **bảng TÀI KHOẢN**
 * — bảng đó liệt kê mọi tài khoản kể cả kế toán, nên nhìn vào **không trả lời được
 * câu "cơ sở nào chưa có PIN"**: phải tự dò tên `cs<mã>` bằng mắt rồi đối chiếu với
 * danh mục cơ sở. Hàm này trả lời thẳng câu đó.
 *
 * ⚠️ Đi từ **DANH MỤC CƠ SỞ** rồi mới tìm tài khoản, KHÔNG đi từ bảng tài khoản. Đi
 * ngược thì cơ sở **chưa có tài khoản nào** sẽ không sinh ra dòng nào — mà đó đúng là
 * dòng cần thấy nhất.
 *
 * ⚠️ Cơ sở **đã ngưng vẫn liệt kê**, chỉ không tính vào `thieuPin`. Ngưng rồi thì
 * không cần PIN, nhưng phải thấy tài khoản của nó **còn sống hay không** — để sống là
 * còn một PIN đăng nhập được vào một cơ sở đã dẹp.
 *
 * ⚠️ **Không trả PIN bản rõ** — sheet chỉ có bản băm, và kể cả có cũng không được trả.
 * Chỉ trả `coPin` đúng/sai. Bản rõ chỉ tồn tại đúng một lần trong `jpTaoPinCoSo`.
 */
function jpPinTheoCoSo(token) {
  jpNeedKT_(jpAuth_(token));

  var users = jpRows_(JP_TABS.USERS);
  var theoTen = {};
  users.forEach(function (r) {
    theoTen[jpStr_(r.username).toLowerCase()] = r;
  });

  var rows = jpRows_(JP_TABS.LOCATIONS).map(function (l) {
    var code    = jpStr_(l.code) || String(l.id);
    var uname   = jpUserCoSo_(code);
    var ex      = theoTen[uname];
    var dangDung = jpStr_(l.active) !== 'N';
    return {
      locationId: String(l.id),
      code: code,
      tenCoSo: jpStr_(l.name) || code,
      loaiMay: jpStr_(l.machineType),
      username: uname,
      coTaiKhoan: !!ex,
      coPin: !!(ex && jpStr_(ex.pin)),
      /* PIN mặc định (`222`/`101`) thì `jpAuth_` KHOÁ mọi cửa cho tới khi đổi — coi
         như chưa dùng được, phải nói ra chứ không được tính là "đã cấp". */
      pinMacDinh: !!(ex && jpStr_(ex.pin) && jpLaPinMacDinh_(jpStr_(ex.pin))),
      taiKhoanActive: !!(ex && jpStr_(ex.active) !== 'N'),
      coSoActive: dangDung
    };
  });

  /* Cơ sở đang chạy mà chưa dùng được PIN — đây là con số kế toán cần */
  var thieuPin = rows.filter(function (r) {
    return r.coSoActive && (!r.coPin || !r.coTaiKhoan || !r.taiKhoanActive);
  }).map(function (r) { return r.code; });

  /* Cơ sở ĐÃ NGƯNG mà tài khoản còn mở — một PIN đăng nhập được vào điểm đã dẹp */
  var ngungConMo = rows.filter(function (r) {
    return !r.coSoActive && r.coTaiKhoan && r.taiKhoanActive;
  }).map(function (r) { return r.code; });

  /*
   * HAI CƠ SỞ CÙNG MỘT TÊN ĐĂNG NHẬP — hỏng im lặng, phải bắt ở đây.
   *
   * `username` suy từ `code`, mà không có gì chặn kế toán đặt trùng `code` ở Cấu hình
   * → Cơ sở. Trùng thì `jpTaoPinCoSo` thấy tài khoản "đã có" ở cơ sở thứ hai và
   * **ghi đè `locationIds` bằng cơ sở chạy sau** ⇒ hai điểm bán dùng CHUNG một PIN,
   * và một trong hai **mất quyền mở báo cáo** mà không báo gì. Không hàm nào khác
   * phát hiện được: đăng nhập vẫn được, chỉ là mở báo cáo ra thì không thấy cơ sở.
   *
   * Chỉ đếm cơ sở ĐANG CHẠY — trùng với một điểm đã dẹp thì không ai đăng nhập nữa.
   */
  var theoUser = {};
  rows.forEach(function (r) {
    if (!r.coSoActive) return;
    (theoUser[r.username] = theoUser[r.username] || []).push(r.code);
  });
  var trungTen = Object.keys(theoUser).filter(function (k) {
    return theoUser[k].length > 1;
  }).map(function (k) { return { username: k, codes: theoUser[k] }; });

  rows.sort(function (a, b) {
    if (a.coSoActive !== b.coSoActive) return a.coSoActive ? -1 : 1;
    return String(a.tenCoSo).localeCompare(String(b.tenCoSo));
  });

  return {
    ok: true, rows: rows,
    soCoSo: rows.length,
    soDangChay: rows.filter(function (r) { return r.coSoActive; }).length,
    thieuPin: thieuPin, ngungConMo: ngungConMo, trungTen: trungTen
  };
}

