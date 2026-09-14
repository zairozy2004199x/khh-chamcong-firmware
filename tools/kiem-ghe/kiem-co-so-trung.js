const { chromium } = require('playwright');
const CS = [
  { id:1, ten:'CGV PEAR PLAZA',  tinh:'Hồ Chí Minh', ma_kh:'KH00247', dong_cua:0, ghe:2 },
  { id:2, ten:'CGV PEARL PLAZA', tinh:'Hồ Chí Minh', ma_kh:'',        dong_cua:0, ghe:1 },
  { id:3, ten:'CGV VINCOM 1',    tinh:'Hồ Chí Minh', ma_kh:'KH00250', dong_cua:0, ghe:3 },
  { id:4, ten:'CGV VINCOM 2',    tinh:'Hồ Chí Minh', ma_kh:'KH00251', dong_cua:0, ghe:3 },
  { id:5, ten:'AEON MALL TÂN PHÚ', tinh:'Hồ Chí Minh', ma_kh:'KH00119', dong_cua:0, ghe:5 },
  { id:6, ten:'LOTTE ĐÃ NGHỈ',   tinh:'Cần Thơ',     ma_kh:'KH00300', dong_cua:1, ghe:2 },
  { id:7, ten:'SHOP NHỎ ĐÓNG',   tinh:'Cần Thơ',     ma_kh:'',        dong_cua:1, ghe:0 }
];
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1500, height:1000 } });
  const loi = []; p.on('pageerror', e => loi.push('PAGEERROR: ' + e.message));
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(250);

  await p.evaluate((CS) => {
    window.__TRALOI['kt_ma_misa_map'] = { ok:true, map:{} };
    window.__TRALOI['coso_gop']  = { ok:true, doi:1, thong_bao:'Đã gộp.' };
    window.__TRALOI['coso_dong'] = { ok:true, thong_bao:'Đã đóng.' };
    /* `lam()` goi `tai()` sau moi tac vu -> `so_lieu`. Khong stub cho tu te thi ban ve lai chay
       voi du lieu rong va nem "reading 'name'" — LOI CUA HARNESS, khong phai cua app. Stub dung
       hinh dang that de con bat duoc loi THAT neu co. */
    window.__TRALOI['so_lieu'] = () => ({ ok:true, coso:CS,
      may: CS.flatMap((c,ci) => Array.from({length:c.ghe}, (_,k) => ({ ma:String(80000+ci*100+k), ten:'M'+ci+'-'+k, coso:c.ten }))),
      /* `ve()` doc D.ai.name/role de ve dau trang — thieu la nem "reading 'name'". */
      tong:{ tong:0, theo_coso:[] }, ai:{ name:'Kiểm', role:'admin' }, cho:[], choGan:[], loi:[], nhat_ky:[] });
    window.__T.setTOK('t');
    /* Phai dat TAB='quan-ly'. Sau moi tac vu `lam()` goi `tai()` -> `ve()` ve lai CA TRANG theo
       TAB hien tai; de mac dinh 'doi-soat' thi bang Dia diem bien mat va nut tiep theo khong con.
       Dat dung tab cung la canh THAT: nguoi dung dang dung o tab Quan ly ghe. */
    window.__T.setTAB('quan-ly');
    window.__T.setD({
      coso: CS,
      may: CS.flatMap((c,ci) => Array.from({length:c.ghe}, (_,k) => ({ ma:String(80000+ci*100+k), ten:'M'+ci+'-'+k, coso:c.ten }))),
      tong:{ tong:0, theo_coso:[] } });
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    window.__T.noi();
  }, CS);
  await p.waitForTimeout(400);

  const r = await p.evaluate(() => {
    const ten = t => { const e = document.getElementById(t); if (!e) return [];
      return [].slice.call((e.tBodies[0]||e).rows).filter(tr => !(tr.cells.length && tr.cells[0].tagName==='TH'))
        .map(tr => { const a = tr.querySelector('[data-csxem]'); return a ? a.getAttribute('data-csxem') : '?'; }); };
    const capNut = [].slice.call(document.querySelectorAll('.btn-gop[data-giu]'))
      .map(b => ({ giu:b.getAttribute('data-giuten'), bo:b.getAttribute('data-boten') }));
    return { chinh: ten('cs-bang'), rong: ten('cs-bang-rong'), dong: ten('cs-bang-dong'),
             soNutGop: capNut.length, capNut,
             coKhoiCanhBao: document.body.textContent.indexOf('CƠ SỞ GẦN TRÙNG TÊN') >= 0,
             soNutCua: document.querySelectorAll('[data-csdong]').length };
  });

  /* Ve lai man hinh SACH truoc moi thao tac. `lam()` goi `tai()` -> `ve()` ve lai CA trang bang
     du lieu gia; bam tiep tren cai xac do la dang kiem mot thu khac han. Moi thao tac mot man
     moi thi tung phep kiem doc lap, hong cai nao biet cai do. */
  async function veLai(){
    await p.evaluate((CS) => {
      window.__T.setD({ coso: CS,
        may: CS.flatMap((c,ci) => Array.from({length:c.ghe}, (_,k) => ({ ma:String(80000+ci*100+k), ten:'M'+ci+'-'+k, coso:c.ten }))),
        tong:{ tong:0, theo_coso:[] }, ai:{ name:'Kiểm', role:'admin' }, cho:[], choGan:[], loi:[], nhat_ky:[] });
      document.getElementById('app').innerHTML = window.__T.veQuanLy();
      window.__T.noi();
    }, CS);
    await p.waitForTimeout(250);
  }

  // ---- Bam GOP: phai hoi HAI lan, va gui dung chieu ----
  let hoi = [];
  /* Dem RIENG hop XAC NHAN cua viec gop. Truoc do dem ca hop bao "Da gop." do `lam()` bat len —
     thanh 7 hop va phep kiem bao hong oan. */
  p.on('dialog', async d => { hoi.push(d.message()); await d.accept(); });
  const hoiGop = () => hoi.filter(m => m.indexOf('GỘP cơ sở') === 0 || m.indexOf('Chắc chắn xoá hẳn') === 0);
  await p.click('.btn-gop[data-boten="CGV PEARL PLAZA"]');
  await p.waitForTimeout(350);
  const gop = await p.evaluate(() => { const g = (window.__GOI||[]).filter(x => x.viec==='coso_gop').pop(); return g ? g.d : null; });

  // ---- Bam 🚪 dong cua ----
  await veLai();
  await p.click('[data-csdong="5"]');
  await p.waitForTimeout(300);
  const dong = await p.evaluate(() => { const g = (window.__GOI||[]).filter(x => x.viec==='coso_dong').pop(); return g ? g.d : null; });
  // ---- Bam 🚪 tren co so DANG dong -> phai la MO LAI (dong:0) ----
  /* Khoi "Co so da dong cua" la <details> GAP SAN — dung nhu thiet ke (ẩn khỏi danh sách).
     Phai mo ra moi bam duoc, y nhu nguoi that phai bam vao dong "🚪 Co so da dong cua". */
  await veLai();
  const daGap = await p.evaluate(() => {
    const t = document.getElementById('cs-bang-dong'); if (!t) return 'khong co bang';
    const d = t.closest('details'); if (!d) return 'khong nam trong details';
    const gapSan = !d.open; d.open = true; return gapSan ? 'gap san' : 'mo san';
  });
  await p.waitForTimeout(150);
  await p.click('[data-csdong="6"]');
  await p.waitForTimeout(300);
  const moLai = await p.evaluate(() => { const g = (window.__GOI||[]).filter(x => x.viec==='coso_dong').pop(); return g ? g.d : null; });

  const kc = await p.evaluate(() => ({
    pearPearl: window.__T.csKhoangCach_('cgvpearplaza','cgvpearlplaza'),
    vincom12:  window.__T.csKhoangCach_('cgvvincom1','cgvvincom2'),
    khacHan:   window.__T.csKhoangCach_('aeonmalltanphu','cgvpearplaza')
  }));

  await p.screenshot({ path:'/tmp/ghetest/trung.png', fullPage:false });
  console.log(JSON.stringify({ loi, r, soLanHoiGop:hoiGop().length, soLanHoi:hoi.length, hoi:hoi.map(h=>h.slice(0,70)), gop, dong, moLai, kc, daGap }, null, 1));
  await b.close();

  const hong = loi.length
    || !r.coKhoiCanhBao
    || r.chinh.indexOf('LOTTE ĐÃ NGHỈ') >= 0            /* dong cua KHONG duoc nam o bang chinh */
    || r.rong.indexOf('SHOP NHỎ ĐÓNG') >= 0             /* dong cua + 0 ghe -> van vao khoi DONG */
    || r.dong.length !== 2
    || r.soNutGop !== 4                                  /* 2 cap x 2 chieu */
    || hoiGop().length !== 2        /* dung HAI hop xac nhan truoc khi xoa mot co so */
    || !gop || gop.nguon !== '2' || gop.dich !== '1'      /* bo PEARL (id2), giu PEAR (id1) */
    || !dong || dong.id !== '5' || dong.dong !== 1
    || !moLai || moLai.id !== '6' || moLai.dong !== 0
    || kc.pearPearl !== 1 || kc.vincom12 !== 1 || kc.khacHan < 3
    || daGap !== 'gap san';   /* khoi da dong cua PHAI gap san — do chinh la "an khoi danh sach" */
  process.exit(hong ? 1 : 0);
})();
