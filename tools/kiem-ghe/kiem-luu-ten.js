const { chromium } = require('playwright');
/* Đúng bộ ghế trong ảnh anh Thắng gửi 14/09/2026 — kể cả "ESTELLA4" gõ thiếu gạch nối, vì chính
   cái tên đó làm lộ ra chỗ sắp xếp sai. */
const MAY = [
  { ma:'80047', ten:'ESTELLA-1', ten_goi:'', coso:'ESTELLA' },
  { ma:'80048', ten:'ESTELLA-2', ten_goi:'', coso:'ESTELLA' },
  { ma:'80049', ten:'ESTELLA-3', ten_goi:'', coso:'ESTELLA' },
  { ma:'80050', ten:'ESTELLA4',  ten_goi:'', coso:'ESTELLA' },
  { ma:'80051', ten:'ESTELLA-5', ten_goi:'', coso:'ESTELLA' },
  { ma:'80052', ten:'ESTELLA-6', ten_goi:'Ghế cạnh thang máy', coso:'ESTELLA' }
];
const KQ = { pass:[], fail:[] };
const ok = (t,c,ghi) => (c ? KQ.pass : KQ.fail).push(t + (ghi ? ' — ' + ghi : ''));

(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1500, height:900 } });
  const loi = []; p.on('pageerror', e => loi.push(String(e)));
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(150);

  async function dung(may){
    await p.evaluate((may) => {
      window.__GOI.length = 0;
      window.__TRALOI['may_ten_lo'] = (q) => ({
        ok:true, xong:(q.ds||[]).length, loi:[],
        /* Máy chủ CHUẨN HOÁ tên sao kê (chuan_ten có thể sửa chữ người gõ). Giả lập bằng một
           thay đổi THẤY ĐƯỢC — viết hoa thì tên trong bài kiểm vốn đã hoa, phép so sẽ đúng cả
           khi màn hình bướng bỉnh giữ chữ thô, tức bài kiểm xanh cho một lỗi còn nguyên. */
        da:(q.ds||[]).reduce((a,g)=>{ const m={};
          if ('ten' in g) m.ten = String(g.ten) + '~sv';
          if ('ten_goi' in g) m.ten_goi = g.ten_goi; a[g.ma]=m; return a; },{}),
        thong_bao:'💾 Đã lưu ' + (q.ds||[]).length + ' ghế.' });
      const D = { coso:[{id:1,ten:'ESTELLA',tinh:'HCM',ma_kh:'',dong_cua:0}],
        may: JSON.parse(JSON.stringify(may)),
        tong:{tong:0,theo_coso:[]}, ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] };
      window.__TRALOI['so_lieu'] = D;
      window.__T.setTOK('t'); window.__T.setTAB('quan-ly'); window.__T.setD(D);
      window.__T.setPG(0); window.__T.setBao('');
      document.getElementById('app').innerHTML = '<div id="ql-wrap"></div>';
      window.__T.qlGheRender();
    }, may);
    await p.waitForTimeout(200);
  }
  const doc = () => p.evaluate(() => ({
    thuTu: [].map.call(document.querySelectorAll('[data-ten]'), i => i.value),
    tenGoi: [].reduce.call(document.querySelectorAll('[data-tengoi]'),
      (a,i)=>{ a[i.getAttribute('data-tengoi')] = i.value; return a; }, {}),
    coThanhLuu: !!document.getElementById('ql-thanhluu'),
    demSua: Object.keys(window.__T.getSua()).length,
    nhan: (document.querySelector('#ql-thanhluu b')||{}).textContent || '',
    bao: ([].filter.call(document.querySelectorAll('#ql-wrap div'),
            d => /Đã lưu/.test(d.textContent))[0]||{}).textContent || '',
    dMay: window.__T.getD().may.map(m => ({ ma:m.ma, ten:m.ten, ten_goi:m.ten_goi })),
    goi: window.__GOI.slice()
  }));

  // ── 1. SẮP XẾP: ESTELLA4 (thiếu gạch nối) phải nằm đúng chỗ thứ 4, không rơi xuống cuối ──
  await dung(MAY);
  let r = await doc();
  ok('Sắp đúng thứ tự dù tên thiếu gạch nối',
     JSON.stringify(r.thuTu) === JSON.stringify(
       ['ESTELLA-1','ESTELLA-2','ESTELLA-3','ESTELLA4','ESTELLA-5','ESTELLA-6']),
     r.thuTu.join(' | '));

  // ── 2. GÕ KHÔNG GỬI GÌ, KHÔNG NHẢY ─────────────────────────────────────────────────────
  await p.fill('[data-tengoi="80047"]', 'Ghế đầu dãy');
  await p.evaluate(() => document.querySelector('[data-tengoi="80047"]').blur());
  await p.waitForTimeout(250);
  r = await doc();
  ok('Gõ xong rời ô: KHÔNG gửi gì lên máy chủ', r.goi.length === 0, 'đã gửi ' + r.goi.length);
  ok('Gõ xong rời ô: chữ vừa gõ CÒN NGUYÊN', r.tenGoi['80047'] === 'Ghế đầu dãy', r.tenGoi['80047']);
  ok('Hiện thanh Lưu khi có thay đổi', r.coThanhLuu && r.demSua === 1, r.nhan);

  // ── 3. ĐỔI TRANG / VẼ LẠI KHÔNG NUỐT PHẦN ĐANG GÕ ─────────────────────────────────────
  await p.fill('[data-ten="80052"]', 'ESTELLA-6B');
  await p.waitForTimeout(150);
  await p.evaluate(() => window.__T.qlGheRender());   // một lượt tự làm mới ập vào giữa chừng
  await p.waitForTimeout(150);
  r = await doc();
  ok('Vẽ lại giữa chừng: giữ CẢ hai ô đang gõ dở',
     r.tenGoi['80047'] === 'Ghế đầu dãy' && r.thuTu.indexOf('ESTELLA-6B') >= 0,
     JSON.stringify(r.thuTu) + ' / ' + r.tenGoi['80047']);
  ok('Đếm đúng 2 ghế đang sửa', r.demSua === 2, String(r.demSua));

  // ── 4. BẤM LƯU: gửi MỘT lượt, gửi đúng khoá, không đụng khoá không sửa ────────────────
  await p.click('#ql-luuten');
  await p.waitForTimeout(350);
  r = await doc();
  ok('Lưu: đúng MỘT lượt gọi máy chủ', r.goi.length === 1, JSON.stringify(r.goi.map(g=>g.viec)));
  ok('Lưu: đi đường may_ten_lo', r.goi[0] && r.goi[0].viec === 'may_ten_lo');
  const ds = (r.goi[0] && r.goi[0].d.ds) || [];
  const g47 = ds.filter(x=>x.ma==='80047')[0] || {}, g52 = ds.filter(x=>x.ma==='80052')[0] || {};
  ok('Lưu: gửi đủ 2 ghế', ds.length === 2, JSON.stringify(ds));
  /* 🔴 Ghế chỉ sửa tên thường gọi thì gói KHÔNG được có khoá `ten`. Có là ghi đè tên sao kê —
     tên đi vào nội dung chuyển khoản, hỏng là tiền cũ thôi ghép được vào ghế. */
  ok('Lưu: ghế chỉ sửa tên thường gọi KHÔNG kèm khoá `ten`',
     ('ten_goi' in g47) && !('ten' in g47), JSON.stringify(g47));
  ok('Lưu: ghế chỉ sửa tên ghế KHÔNG kèm khoá `ten_goi`',
     ('ten' in g52) && !('ten_goi' in g52), JSON.stringify(g52));

  // ── 5. LƯU XONG: cập nhật TẠI CHỖ, không gọi lại so_lieu, lấy bản CHUẨN HOÁ của máy chủ ──
  ok('Lưu xong: KHÔNG gọi lại so_lieu (không vẽ lại cả trang)',
     r.goi.filter(g=>g.viec==='so_lieu').length === 0);
  ok('Lưu xong: thanh Lưu biến mất, giỏ rỗng', !r.coThanhLuu && r.demSua === 0);
  ok('Lưu xong: hiện câu "đã lưu"', /Đã lưu 2 ghế/.test(r.bao), r.bao);
  const m52 = r.dMay.filter(m=>m.ma==='80052')[0] || {};
  ok('Lưu xong: lấy tên ĐÃ CHUẨN HOÁ của máy chủ, không giữ chữ thô',
     m52.ten === 'ESTELLA-6B~sv', m52.ten);
  ok('Lưu xong: ô tên ghế hiện bản của máy chủ',
     r.thuTu.indexOf('ESTELLA-6B~sv') >= 0, JSON.stringify(r.thuTu));
  ok('Lưu xong: tên thường gọi vào D.may',
     (r.dMay.filter(m=>m.ma==='80047')[0]||{}).ten_goi === 'Ghế đầu dãy');
  ok('Lưu xong: ô trên màn khớp bản đã lưu', r.tenGoi['80047'] === 'Ghế đầu dãy', r.tenGoi['80047']);

  // ── 6. TÊN THƯỜNG GỌI TỪ MÁY CHỦ PHẢI HIỆN RA (gốc lỗi "không lưu được") ──────────────
  await dung(MAY);
  r = await doc();
  ok('Tên thường gọi máy chủ gửi về có HIỆN ra ô', r.tenGoi['80052'] === 'Ghế cạnh thang máy',
     JSON.stringify(r.tenGoi));

  // ── 7. LƯU HỎNG: giữ nguyên phần đã gõ, không nuốt công người ta ──────────────────────
  await p.evaluate(() => { window.__TRALOI['may_ten_lo'] = { ok:false, error:'Mạng rớt' }; });
  p.once('dialog', d => d.accept());
  await p.fill('[data-tengoi="80048"]', 'Ghế giữa');
  await p.waitForTimeout(150);
  await p.click('#ql-luuten');
  await p.waitForTimeout(350);
  r = await doc();
  ok('Lưu hỏng: GIỮ chữ vừa gõ', r.tenGoi['80048'] === 'Ghế giữa', r.tenGoi['80048']);
  ok('Lưu hỏng: giỏ sửa còn nguyên', r.demSua === 1, String(r.demSua));
  ok('Lưu hỏng: nút Lưu bấm lại được',
     await p.evaluate(() => { const b=document.getElementById('ql-luuten'); return !!b && !b.disabled; }));

  // ── 8. Bảng không lệch cột ────────────────────────────────────────────────────────────
  const cot = await p.evaluate(() => {
    const th = document.querySelectorAll('#ql-wrap table tr:first-child th').length;
    const xau = [].filter.call(document.querySelectorAll('#ql-wrap table tr'),
      (tr,i) => i > 0 && tr.querySelectorAll('td').length !== th && !tr.querySelector('td[colspan]'));
    return { th, xau: xau.length };
  });
  ok('Số ô mỗi hàng khớp số cột tiêu đề', cot.xau === 0, JSON.stringify(cot));

  ok('Không có lỗi JS trên trang', loi.length === 0, loi.join(' | '));
  await p.screenshot({ path:'/tmp/ghetest/luu-ten.png' });
  await b.close();
  console.log('PASS ' + KQ.pass.length + ' / FAIL ' + KQ.fail.length);
  KQ.pass.forEach(t => console.log('  ✓ ' + t));
  KQ.fail.forEach(t => console.log('  ✗ ' + t));
  process.exit(KQ.fail.length ? 1 : 0);
})();
