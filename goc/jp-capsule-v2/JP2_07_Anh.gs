/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  07_Anh
 * ---------------------------------------------------------------------------
 * Upload ảnh báo cáo. Điểm lớn nhất (SB Phú Quốc) tới ~138 ảnh/báo cáo.
 *
 * Apps Script giới hạn 6 phút/lần chạy và payload ~50MB ⇒ TUYỆT ĐỐI không
 * upload cả loạt. Client nén ảnh xuống ~1280px/JPEG 70% rồi gửi TỪNG ẢNH MỘT.
 *
 *  · Ảnh chỉ số  (METER)  → gán theo Ô/MÁY   (scope = 'ROW')
 *  · Ảnh Pay Box (PAYBOX) → gán theo CỤM     (scope = 'ZONE')
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① THƯ MỤC DRIVE ────────────────────*/

function jpPhotoRoot_() {
  if (JP_PHOTO_ROOT_ID) {
    try { return DriveApp.getFolderById(JP_PHOTO_ROOT_ID); } catch (e) {}
  }
  var p = PropertiesService.getScriptProperties();
  var id = p.getProperty('JP_PHOTO_ROOT');
  if (id) {
    try { return DriveApp.getFolderById(id); } catch (e) {}
  }
  var it = DriveApp.getFoldersByName(JP_PHOTO_ROOT_NAME);
  var f = it.hasNext() ? it.next() : DriveApp.createFolder(JP_PHOTO_ROOT_NAME);
  p.setProperty('JP_PHOTO_ROOT', f.getId());
  return f;
}

function jpSubFolder_(parent, name) {
  var it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}

/** JP Capsule - Anh bao cao / <Cơ sở> / <từ ngày _ đến ngày> / */
function jpReportFolder_(head) {
  var loc = jpStr_(head.locationName) || 'Khong ro';
  var period = jpDate_(head.fromDate) + '_' + jpDate_(head.toDate);
  return jpSubFolder_(jpSubFolder_(jpPhotoRoot_(), loc), period);
}

/*──────────────────── ② UPLOAD 1 ẢNH ────────────────────*/

function jpUploadPhoto(token, p) {
  var u = jpAuth_(token);
  p = p || {};

  var head = jpFindOne_(JP_TABS.REPORTS, 'id', p.reportId);
  if (!head) throw new Error('Không tìm thấy báo cáo');
  if (jpIsNV_(u)) {
    jpNeedLoc_(u, head.locationId);
    if (!jpCanEditReport_(u, head)) throw new Error('Báo cáo đã nộp, không thêm ảnh được');
  }

  var m = String(p.dataUrl || '').match(/^data:([^;]+);base64,(.+)$/);
  if (!m) throw new Error('Ảnh không hợp lệ');

  var bytes = Utilities.base64Decode(m[2]);
  if (bytes.length > 3 * 1024 * 1024) {
    throw new Error('Ảnh quá lớn (' + Math.round(bytes.length / 1024) + 'KB) — nén lại trước khi gửi');
  }

  var scope = jpStr_(p.scope) || 'ROW';
  var kind  = jpStr_(p.kind) || JP_PHOTO_METER;
  var name  = [head.id, scope, jpStr_(p.refId), kind,
               Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'HHmmss')]
              .join('_') + '.jpg';

  var blob = Utilities.newBlob(bytes, m[1], name);
  var file = jpReportFolder_(head).createFile(blob);

  var rec = {
    id: jpNextId_('PH'),
    reportId: head.id, scope: scope, refId: jpStr_(p.refId), kind: kind,
    fileId: file.getId(),
    /* Vẫn ghi cột `url` cho dữ liệu cũ / chỗ nào còn đọc thẳng sheet. Nhưng chỗ HIỂN
       THỊ thì `jpPubPhoto_` DỰNG LẠI từ `fileId` — nên đổi `JP_ANH_SZ_NHO` là ăn cho
       cả ảnh đã tải lên trước đó, không phải sửa lại cột này. */
    url: jpAnhUrl_(file.getId(), JP_ANH_SZ_NHO),
    takenAt: jpStr_(p.takenAt), uploadedAt: new Date(), bytes: bytes.length
  };
  jpAppend_(JP_TABS.PHOTOS, rec);

  return { ok: true, photo: jpPubPhoto_(rec) };
}

/*──────────────────── ③ XOÁ / CHỤP LẠI ────────────────────*/

function jpDeletePhoto(token, photoId) {
  var u = jpAuth_(token);
  var rec = jpFindOne_(JP_TABS.PHOTOS, 'id', photoId);
  if (!rec) return { ok: true };

  var head = jpFindOne_(JP_TABS.REPORTS, 'id', rec.reportId);
  if (jpIsNV_(u)) {
    if (!head) throw new Error('Không tìm thấy báo cáo của ảnh này');
    jpNeedLoc_(u, head.locationId);
    if (!jpCanEditReport_(u, head)) throw new Error('Báo cáo đã nộp, không xoá ảnh được');
  }

  try { DriveApp.getFileById(rec.fileId).setTrashed(true); } catch (e) {}
  jpDelete_(JP_TABS.PHOTOS, rec._row);
  jpAudit_(u, 'PHOTO_DELETE', rec.reportId, rec.fileId, '');
  return { ok: true };
}

/*──────────────────── ③b ĐỌC ẢNH QUA WEB APP ────────────────────
 *
 * Andy 11/08/2026: *"lỗi ảnh tại web kế toán"* — khung xem ảnh in
 * *"Không tải được ảnh này ở đây"*.
 *
 * ⚠️⚠️ NGUYÊN NHÂN GỐC, và nó KHÔNG phải cỡ ảnh: **file ảnh chỉ có ĐÚNG MỘT
 * quyền — chủ sở hữu.** Đã tra Drive: cả file `RP20260810-0021_ROW_…jpg` lẫn thư
 * mục gốc `JP Capsule - Anh bao cao` đều chỉ có
 * `haihan@poshvn.com · role owner`, không có `anyone with link`.
 *
 * Mà `https://drive.google.com/thumbnail?id=…` là **trình duyệt của NGƯỜI XEM tự
 * đi tải**, mang phiên Google của chính họ — không mang quyền của web app. Nên:
 *
 *   · người xem không phải chủ sở hữu ⇒ Drive trả **403** ⇒ ảnh vỡ
 *   · kể cả chủ sở hữu, web app chạy trong iframe `googleusercontent.com` nên
 *     cookie bên thứ ba tới `drive.google.com` có thể bị trình duyệt chặn
 *
 * ⇒ Đây là lỗi của **cả tính năng ảnh**, không riêng khung xem: ảnh nhỏ ở dải
 * (`sz=w600`) vỡ y hệt, chỉ là ô xám trông như ảnh đang tải nên không ai báo.
 *
 * Cách sửa **không phải đổi quyền file**: web app chạy `executeAs: USER_DEPLOYING`
 * nên `DriveApp` ở đây CÓ quyền. Đọc bytes rồi trả về data URI — người xem không
 * cần quyền Drive nào, và không có lượt tải chéo miền nào để bị chặn.
 *
 * ⚠️ **CHỐT BẮT BUỘC: chỉ đọc file có mặt trong bảng `JP_Photos`.** Thiếu chốt này
 * thì bất kỳ ai có token đều đọc được **MỌI file trong Drive của chủ sở hữu** chỉ
 * bằng cách đoán `fileId` — đúng loại lỗ hổng đã soát ở mục "Hàm client gọi được
 * PHẢI kiểm quyền". Và nhân viên thì chỉ được xem ảnh của cơ sở mình.
 */
function jpAnhXem(token, fileId) {
  var u = jpAuth_(token);
  var id = jpStr_(fileId);
  if (!id) throw new Error('Thiếu mã file ảnh');

  var rec = jpFindOne_(JP_TABS.PHOTOS, 'fileId', id);
  if (!rec) throw new Error('File này không thuộc ảnh báo cáo nào');

  if (jpIsNV_(u)) {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', rec.reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo của ảnh này');
    jpNeedLoc_(u, head.locationId);
  }

  var blob;
  try { blob = DriveApp.getFileById(id).getBlob(); }
  catch (e) { throw new Error('Không đọc được file trên Drive: ' + (e && e.message)); }

  var bytes = blob.getBytes();
  /* Ảnh do app tải lên bị chặn ở 3MB, nên mọi ảnh bình thường đều qua. Ảnh cũ
     hoặc ai đó bỏ tay vào thư mục thì phải NÓI RA thay vì để lượt gọi vỡ giữa
     đường — vẫn còn đường "Mở trong Drive". */
  if (bytes.length > JP_ANH_XEM_MAX) {
    throw new Error('Ảnh quá lớn để xem tại đây (' +
      Math.round(bytes.length / 1024 / 1024 * 10) / 10 + 'MB) — bấm "Mở trong Drive"');
  }

  return {
    ok: true, fileId: id, bytes: bytes.length,
    dataUrl: 'data:' + (blob.getContentType() || 'image/jpeg') + ';base64,' +
             Utilities.base64Encode(bytes)
  };
}

/*──────────────────── ③d ẢNH THEO CƠ SỞ ────────────────────
 *
 * Andy 11/08/2026: *"làm màn xem hết ảnh của một cơ sở đi em"*.
 *
 * Trước bản này ảnh **chỉ xem được từ trong một báo cáo**. Muốn soi lại ảnh chỉ số
 * của một cơ sở qua mấy kỳ là phải mở lần lượt từng báo cáo — mà đối chiếu thì đúng
 * là phải xem liền nhau mới thấy chỉ số nhảy có đều hay không.
 *
 * ⚠️ **2 lượt Sheets, KHÔNG phình theo số ảnh**: đọc `JP_Reports` một lần (lọc cơ sở
 * + khoảng ngày) rồi `JP_Photos` một lần, khớp theo `reportId`. Đừng "dọn" thành vòng
 * lặp gọi `jpPhotoProgress` cho từng báo cáo — 20 kỳ là ~60 lượt, và tên cơ sở đã có
 * sẵn trên `JP_Reports.locationName` nên cũng không cần đọc bảng cơ sở.
 *
 * ⚠️ **Báo cáo KHÔNG CÓ ẢNH NÀO vẫn phải liệt kê** (`khongCoAnh`). Một màn chỉ in
 * những ảnh CÓ thì không bao giờ nói được điều quan trọng nhất: kỳ nào cơ sở không
 * gửi ảnh. Cùng luật với `jpKhoNhapXuatTon` (mọi `(kho, mã)` có tồn đều phải có dòng)
 * và với thẻ kho (mã có tồn mà không phát sinh vẫn giữ khối).
 *
 * ⚠️ **Có TRẦN và phải NÓI RA khi cắt.** SB Phú Quốc tới ~138 ảnh một kỳ; 20 kỳ là
 * gần 3.000 thẻ `<img>` — trình duyệt ở mall không chịu nổi. Cắt im lặng thì màn
 * trông như "cơ sở chỉ gửi có thế", đúng loại lỗi im lặng nhất.
 */
var JP_ANH_CS_TRAN = 500;

function jpAnhTheoCoSo(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var loc = jpStr_(p.locationId);
  var tu  = jpDate_(p.tuNgay) || '2000-01-01';
  var den = jpDate_(p.denNgay) || '2999-12-31';
  if (tu > den) throw new Error('Từ ngày phải trước đến ngày');
  var kind = jpStr_(p.kind);

  /* Lọc báo cáo theo cơ sở + kỳ. `fromDate` là mốc kỳ, dùng nó cho cùng luật với
     danh sách duyệt — lọc theo ngày TẢI ẢNH thì ảnh chụp bù hôm sau rơi ra ngoài kỳ. */
  var bc = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (loc && jpStr_(r.locationId) !== loc) return false;
    var d = jpDate_(r.fromDate);
    return d >= tu && d <= den;
  }).sort(function (a, b) {
    return String(b.fromDate).localeCompare(String(a.fromDate)) ||
           String(b.id).localeCompare(String(a.id));      // kỳ mới nhất lên đầu
  });

  var idx = {};
  bc.forEach(function (r) { idx[String(r.id)] = []; });

  jpRows_(JP_TABS.PHOTOS).forEach(function (a) {
    var rid = String(a.reportId);
    if (!(rid in idx)) return;                            // ảnh của báo cáo ngoài kỳ
    if (kind && jpStr_(a.kind) !== kind) return;
    idx[rid].push(a);
  });

  var nhom = [], khongCoAnh = [], soAnh = 0, boQua = 0, tenCoSo = '';
  bc.forEach(function (r) {
    var ds = idx[String(r.id)];
    if (!tenCoSo) tenCoSo = jpStr_(r.locationName);
    if (!ds.length) {
      khongCoAnh.push({ reportId: String(r.id), fromDate: jpDate_(r.fromDate),
                        toDate: jpDate_(r.toDate), status: jpStr_(r.status),
                        userName: jpStr_(r.userName),
                        locationName: jpStr_(r.locationName) });
      return;
    }
    /* Xếp trong một báo cáo: cùng LOẠI rồi cùng Ô/MÁY nằm cạnh nhau — soi chỉ số của
       một ô máy qua mấy tấm là việc hay làm nhất. */
    ds.sort(function (a, b) {
      return jpStr_(a.kind).localeCompare(jpStr_(b.kind)) ||
             String(a.refId).localeCompare(String(b.refId)) ||
             String(a.id).localeCompare(String(b.id));
    });
    var giu = [];
    ds.forEach(function (a) {
      if (soAnh >= JP_ANH_CS_TRAN) { boQua++; return; }
      soAnh++;
      giu.push(jpPubPhoto_(a));
    });
    nhom.push({
      reportId: String(r.id), fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
      status: jpStr_(r.status), userName: jpStr_(r.userName),
      machineType: jpStr_(r.machineType), locationName: jpStr_(r.locationName),
      soAnh: ds.length, anh: giu
    });
  });

  return {
    ok: true, locationId: loc, tenCoSo: tenCoSo, tuNgay: tu, denNgay: den, kind: kind,
    soBaoCao: bc.length, soAnh: soAnh, boQua: boQua, tran: JP_ANH_CS_TRAN,
    nhom: nhom, khongCoAnh: khongCoAnh
  };
}

/*──────────────────── ③c CHIA SẺ THƯ MỤC ẢNH ────────────────────
 *
 * Andy 11/08/2026: *"chia sẻ thư mục đó cho kế toán luôn đi em"*.
 *
 * Sau bản `jpAnhXem`, **bản LỚN đã xem được rồi** — nó đọc qua máy chủ nên không cần
 * quyền Drive nào. Chia sẻ thư mục là để **ẢNH NHỎ ở dải cũng hiện**, vì ảnh nhỏ cố ý
 * vẫn trỏ thẳng sang Drive (138 ảnh × một lượt gọi máy chủ mỗi ảnh là chạm giới hạn
 * 6 phút của Apps Script).
 *
 * ⚠️ **Hàm ADMIN, chạy TAY từ trình chỉnh sửa Apps Script** — cùng loại với
 * `jpSetup_` · `jpNapCoSoJP_` · `jpDatCauHinh_`. Dấu `_` cuối tên chặn
 * `google.script.run`, nên **không** có đường nào từ internet gọi tới nó. Mở quyền
 * chia sẻ dữ liệu vận hành là việc một lần, phải có người chủ động bấm — đừng bỏ dấu
 * `_` để "làm cho tiện", và đừng gắn nút trên web.
 *
 * ⚠️ Đặt quyền ở **thư mục GỐC**: thư mục con theo cơ sở / theo kỳ và mọi ảnh mới đều
 * thừa hưởng. Đặt từng file là ảnh chụp sau hôm nay lại vỡ tiếp.
 *
 * Ba chế độ, và chúng **không thay thế được nhau** — chọn sai thì hoặc không sửa được
 * gì, hoặc mở rộng hơn mức cần:
 *
 * | Chế độ | Ai xem được | Khi nào đúng |
 * |---|---|---|
 * | `NGUOI` | đúng địa chỉ được thêm | biết chắc kế toán mở web bằng tài khoản nào |
 * | `DOMAIN` | ai trong `poshvn.com` có link | không rõ tài khoản nào, nhưng chắc là người trong công ty |
 * | `LINK` | **bất kỳ ai có link** | máy kế toán không đăng nhập tài khoản công ty nào |
 *
 * ⚠️ Ảnh nhỏ do **trình duyệt người xem** tải, mang phiên Google của chính họ. Nên
 * `NGUOI` và `DOMAIN` chỉ ăn khi máy đó **đang đăng nhập** tài khoản tương ứng. Máy ở
 * mall thường không đăng nhập gì — lúc đó chỉ `LINK` mới làm ảnh nhỏ hiện ra.
 * Nói ra chỗ này thay vì để người dùng bấm rồi tưởng hỏng.
 */
var JP_CHIA_SE_CHE_DO = ['NGUOI', 'DOMAIN', 'LINK'];

function jpChiaSeAnh_(cheDo, email) {
  var mode = jpStr_(cheDo).toUpperCase();
  if (JP_CHIA_SE_CHE_DO.indexOf(mode) < 0) {
    throw new Error('Chế độ phải là một trong: ' + JP_CHIA_SE_CHE_DO.join(' / '));
  }
  var f = jpPhotoRoot_();

  if (mode === 'NGUOI') {
    var mail = jpStr_(email);
    /* Chặn địa chỉ gõ sai: cấp quyền cho một địa chỉ lạ là đưa ảnh vận hành cho
       người không liên quan, mà Drive thì nhận bất cứ chuỗi nào trông giống email. */
    if (!/^[^@\s]+@[^@\s.]+\.[^@\s]+$/.test(mail)) {
      throw new Error('Thiếu / sai địa chỉ email — ví dụ jpChiaSeAnh_("NGUOI", "kthcm@poshvn.com")');
    }
    f.addViewer(mail);
  } else if (mode === 'DOMAIN') {
    f.setSharing(DriveApp.Access.DOMAIN_WITH_LINK, DriveApp.Permission.VIEW);
  } else {
    f.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  }

  var ra = jpQuyenAnh_();
  /* Ghi audit: đây là một lần MỞ QUYỀN, phải có dấu vết ai làm và mở tới đâu.
     Email không phải bí mật kiểu PIN nên ghi được — nhưng vẫn đi qua `jpAudit_`
     chứ không ghi thêm chỗ nào khác. */
  try {
    jpAudit_({ id: 'ADMIN', hoTen: 'Chạy tay' }, 'ANH_CHIA_SE', f.getId(), mode,
             JSON.stringify({ email: mode === 'NGUOI' ? jpStr_(email) : '',
                              access: ra.access, permission: ra.permission }));
  } catch (e) {}

  return Object.assign({ ok: true, cheDo: mode, thuMuc: f.getName(), id: f.getId(),
    msg: 'Đã chia sẻ thư mục ảnh (' + mode + '). Ảnh NHỎ chỉ hiện khi máy người xem ' +
         'đang đăng nhập tài khoản có quyền — bản LỚN thì luôn xem được vì nó đọc ' +
         'qua máy chủ.' }, ra);
}

/** Thu lại quyền đã mở — mở quyền thì phải có đường đóng, không thì không ai dám bấm. */
function jpBoChiaSeAnh_(email) {
  var f = jpPhotoRoot_();
  var mail = jpStr_(email);
  if (mail) f.removeViewer(mail);
  else f.setSharing(DriveApp.Access.PRIVATE, DriveApp.Permission.VIEW);
  return Object.assign({ ok: true, thuMuc: f.getName(),
    msg: mail ? 'Đã bỏ quyền xem của ' + mail : 'Đã đóng lại, chỉ chủ sở hữu xem được'
  }, jpQuyenAnh_());
}

/*── Bốn hàm KHÔNG THAM SỐ để bấm Run ngay trong trình chỉnh sửa ──
 *
 * ⚠️ **Nút Run của Apps Script gọi hàm KHÔNG có tham số nào.** Nên
 * `jpChiaSeAnh_('LINK')` không chọn từ menu hàm được — chọn `jpChiaSeAnh_` rồi Run là
 * `cheDo` rỗng ⇒ throw. Muốn truyền tham số thì phải SỬA CODE TRONG EDITOR, mà điều đó
 * repo này cấm (lần deploy sau ghi đè).
 *
 * Nên mỗi chế độ một hàm gói sẵn: chọn tên → Run → đọc Execution log.
 * Địa chỉ kế toán để **trong code** chứ không gõ tay lúc chạy — gõ tay một lần là có
 * ngày gõ sai một chữ rồi cấp quyền cho địa chỉ lạ.
 */
function jpChiaSeAnhLink_()   { return jpChiaSeAnh_('LINK'); }
function jpChiaSeAnhDomain_() { return jpChiaSeAnh_('DOMAIN'); }
function jpChiaSeAnhKeToan_() { return jpChiaSeAnh_('NGUOI', JP_MAIL_KE_TOAN); }
function jpDongChiaSeAnh_()   { return jpBoChiaSeAnh_(); }

/** Ai đang xem được thư mục ảnh — để soi lại sau khi đổi, đừng đoán. */
function jpQuyenAnh_() {
  var f = jpPhotoRoot_();
  var ds = [];
  try {
    f.getViewers().forEach(function (u) { ds.push(u.getEmail() + ' · xem'); });
    f.getEditors().forEach(function (u) { ds.push(u.getEmail() + ' · sửa'); });
  } catch (e) {}
  return {
    access: String(f.getSharingAccess()), permission: String(f.getSharingPermission()),
    nguoi: ds
  };
}

/*──────────────────── ④ TIẾN ĐỘ ẢNH ────────────────────*/

/**
 * Cảnh báo thiếu ảnh của 1 báo cáo. Dùng chung cho màn tiến độ ảnh (nhân viên)
 * và cho lúc nộp — nộp thì chốt vào cột photoWarnJson để kế toán còn thấy.
 */
function jpPhotoWarns_(reportId, locationId) {
  var rows   = jpFind_(JP_TABS.ROWS, 'reportId', reportId);
  var zones  = jpFind_(JP_TABS.ZONES, 'reportId', reportId);
  var photos = jpFind_(JP_TABS.PHOTOS, 'reportId', reportId);

  var machineMap = {}, clusterMap = {};
  jpRows_(JP_TABS.MACHINES).forEach(function (m) { machineMap[String(m.id)] = m; });
  jpRows_(JP_TABS.CLUSTERS).forEach(function (c) { clusterMap[String(c.id)] = c; });

  /*
   * `locationId` để `jpCheckPhotos_` biết cơ sở này CÓ cụm nhận QR hay không (W17).
   *
   * ⚠️ Người gọi không đưa thì **TỰ ĐỌC**, đừng bỏ qua im lặng — bỏ qua là thêm một
   * người gọi mới ở đâu đó là W17 tắt mà không ai biết. Đọc lại không tốn lượt Sheets:
   * `jpRows_` cache trong một lần chạy, mà cả hai người gọi hiện tại đều đã đọc
   * `JP_Reports` trước đó rồi.
   */
  var locId = jpStr_(locationId);
  if (!locId) {
    var h = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    locId = h ? jpStr_(h.locationId) : '';
  }

  return {
    warns: jpCheckPhotos_(rows, zones, photos, machineMap, clusterMap, locId),
    rows: rows, zones: zones, photos: photos,
    machineMap: machineMap, clusterMap: clusterMap
  };
}

function jpPhotoProgress(token, reportId) {
  var u = jpAuth_(token);
  var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
  if (!head) throw new Error('Không tìm thấy báo cáo');
  if (!jpIsKT_(u)) jpNeedLoc_(u, head.locationId);

  var d = jpPhotoWarns_(reportId, head.locationId);
  var rows = d.rows, zones = d.zones, photos = d.photos;
  var machineMap = d.machineMap, clusterMap = d.clusterMap;
  var warns = d.warns;

  var need = 0;
  rows.forEach(function (r) {
    var k_ = jpStr_(r.rowKind);
    /* Bảng tồn kho và kho ngoài không có đồng hồ ⇒ không cần ảnh chỉ số */
    if (k_ === JP_ROW_STOCK || k_ === JP_ROW_NGOAI) return;
    var m = machineMap[String(r.machineId)];
    need += m ? jpSoAnh_(m.photoCount) : 1;
  });
  zones.forEach(function (z) {
    var c = clusterMap[String(z.clusterId)];
    need += c ? jpSoAnh_(c.photoCount, jpStr_(c.hasQR) === 'Y' ? 1 : 0) : 0;
  });

  /*
   * ⚠️ `missing` là **SỐ CHỖ THIẾU ẢNH**, nên chỉ đếm `MISSING_PHOTO` — đừng đếm cả
   * `warns.length`. Từ 08/09/2026 `jpCheckPhotos_` còn trả `THIEU_CUM_QR` (W17), mà
   * cái đó KHÔNG phải một chỗ thiếu ảnh: nó nói *"chưa chọn cụm nên không có chỗ nào
   * để gắn"*. Đếm cả vào là dòng trên màn in "còn thiếu N+1 chỗ" trong khi chỉ có N ô
   * ảnh trống — con số đúng một chút mà sai, tệ hơn là không có.
   *
   * ⚠️ Vẫn trả **đủ** `warns` — W17 phải hiện ra ở khối cảnh báo, chỉ là không đếm vào
   * con số "thiếu mấy chỗ".
   */
  var soThieuAnh = warns.filter(function (w) {
    return jpStr_(w.code) === JP_WARN.MISSING_PHOTO.code;
  }).length;

  return {
    ok: true, need: need, got: photos.length,
    missing: soThieuAnh, warns: warns
  };
}

/*──────────────────── ⑤ DỌN ẢNH MỒ CÔI ────────────────────*/

function jpCleanOrphanPhotos(token, reportId) {
  jpNeedKT_(jpAuth_(token));
  var rowIds = {}, zoneIds = {};
  jpFind_(JP_TABS.ROWS, 'reportId', reportId).forEach(function (r) { rowIds[String(r.id)] = 1; });
  jpFind_(JP_TABS.ZONES, 'reportId', reportId).forEach(function (z) { zoneIds[String(z.id)] = 1; });

  var photos = jpFind_(JP_TABS.PHOTOS, 'reportId', reportId);
  var orphan = photos.filter(function (p) {
    var s = jpStr_(p.scope);
    return s === 'ROW' ? !rowIds[String(p.refId)] : !zoneIds[String(p.refId)];
  });

  /* Xoá cả file Drive, không chỉ bản ghi — điểm lớn nhất tới ~138 ảnh/báo cáo,
     bỏ file lại là rác tích trong Drive mãi. */
  orphan.sort(function (a, b) { return b._row - a._row; })
        .forEach(function (p) {
          if (jpStr_(p.fileId)) {
            try { DriveApp.getFileById(p.fileId).setTrashed(true); } catch (e) {}
          }
          jpDelete_(JP_TABS.PHOTOS, p._row);
        });

  return { ok: true, removed: orphan.length };
}


