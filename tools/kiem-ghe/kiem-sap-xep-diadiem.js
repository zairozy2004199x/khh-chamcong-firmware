const { chromium } = require('playwright');
// Co so co chu dich, co cai da co Unit ID co cai chua — dung canh anh Thang dang lam
const CS = [
  { ten:'AEON MALL BÌNH DƯƠNG', ghe:12, uid:'51AMBD', misa:'POSH MN AEON MALL BÌNH DƯƠNG' },
  { ten:'BỆNH VIỆN 175',        ghe:12, uid:'50BV175', misa:'POSH MN BỆNH VIỆN 175' },
  { ten:'Cali Thảo Điền',       ghe:4,  uid:'',        misa:'' },
  { ten:'AEON MALL TÂN PHÚ',    ghe:27, uid:'50AMTP',  misa:'POSH MN AEON MALL TÂN PHÚ' },
  { ten:'ĐÀ NẴNG CENTER',       ghe:2,  uid:'',        misa:'' },
  { ten:'BV UNG BƯỚU',          ghe:20, uid:'50BVUB',  misa:'POSH MN BỆNH VIỆN UNG BƯỚU HCM' }
];
const ten = tr => { const a = tr.querySelector('[data-csxem]'); return a ? a.getAttribute('data-csxem') : null; };
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1500, height:900 } });
  const loi = []; p.on('pageerror', e => loi.push('PAGEERROR: ' + e.message));
  await p.goto('file://' + __dirname + '/trang.html'); await p.waitForTimeout(300);
  await p.evaluate((CS) => {
    const map = {}; CS.forEach(c => map[c.ten] = { unit_id:c.uid, unit_name:c.misa });
    window.__TRALOI['kt_ma_misa_map'] = { ok:true, map };
    window.__TRALOI['kt_ma_misa_dat'] = d => ({ ok:true });
    window.__T.setTOK('t');
    window.__T.setD({
      coso: CS.map((c,i) => ({ id:i+1, ten:c.ten, tinh:'HCM', ma_kh:'KH'+i })),
      /* co so "DA NANG CENTER" co 0 ghe -> roi xuong bang gap, de kiem ca bang do */
      /* SO GHE PHAI KHAC NHAU THAT. Ban dau em cat con Math.min(ghe,3) cho gon -> moi co so deu
         3 ghe, nen kieu "nhieu ghe nhat" khong the doi thu tu duoc, va phep kiem bao hong OAN. */
      may: CS.filter(c => c.ghe > 0 && c.ten !== 'ĐÀ NẴNG CENTER')
             .flatMap(c => Array.from({length:c.ghe}, (_,k) => ({ ma:'8'+c.ghe+'-'+k, ten:'X', coso:c.ten })))
             .concat([{ ma:'89999', ten:'L', coso:'' }]),
      tong:{ tong:0, theo_coso:[] } });
    document.getElementById('app').innerHTML = window.__T.veQuanLy();
    /* Gọi noi() — đúng đường thật của app (xem `if (TAB === 'quan-ly')`), mọi sự kiện gắn trong đó.
       Gọi thẳng misaNap() thì ô chọn sắp xếp KHÔNG có handler nào, phép kiểm xanh oan cho nút chết. */
    try { window.__T.noi(); } catch (e) { window.__LOI_NOI = String(e); }
  }, CS);
  await p.waitForTimeout(450);

  async function thuTu(kieu){
    if (kieu) await p.selectOption('#cs-sap', kieu);
    await p.waitForTimeout(120);
    return p.evaluate(() => {
      const lay = id => { const t = document.getElementById(id); if (!t) return [];
        return [].slice.call((t.tBodies[0]||t).rows)
          .filter(tr => !(tr.cells.length && tr.cells[0].tagName === 'TH'))
          .map(tr => { const a = tr.querySelector('[data-csxem]'); return a ? a.getAttribute('data-csxem') : '?'; }); };
      return { chinh: lay('cs-bang'), rong: lay('cs-bang-rong') };
    });
  }

  const r = {};
  r.macDinh = await thuTu(null);
  r.ten     = await thuTu('ten');
  r.thieu   = await thuTu('thieu');
  r.unit    = await thuTu('unit');
  r.misa    = await thuTu('misa');
  r.ghe     = await thuTu('ghe');

  // sau khi GÕ mot Unit ID moi thi sap lai phai theo gia tri MOI
  await p.selectOption('#cs-sap', 'unit');
  await p.fill('[data-misau="Cali Thảo Điền"]', '01AAA');
  await p.evaluate(() => document.querySelector('[data-misau="Cali Thảo Điền"]').blur());
  await p.waitForTimeout(250);
  /* 🔴 GÕ XONG KHÔNG ĐƯỢC TỰ SẮP LẠI. Đang điền lần lượt từ trên xuống mà mỗi lần rời ô là hàng
     nhảy đi chỗ khác thì không ai điền nổi. Thứ tự chỉ đổi khi anh CHỌN LẠI kiểu sắp. */
  r.sauKhiGo_giuNguyen = await thuTu(null);
  await p.selectOption('#cs-sap', 'ten'); await p.waitForTimeout(80);
  r.sauKhiChonLai = await thuTu('unit');

  // nho lua chon qua lan tai lai
  const daNho = await p.evaluate(() => { try { return localStorage.getItem('vhg_cs_sap'); } catch(e){ return null; } });

  await p.screenshot({ path:'/tmp/ghetest/sap.png', clip: await p.evaluate(() => {
    const e = document.getElementById('cs-bang'); const b = e.getBoundingClientRect();
    return { x:Math.max(0,b.x-8), y:Math.max(0,b.y-56), width:Math.min(b.width+16, innerWidth), height:Math.min(b.height+64, 560) }; }) });
  const loiNoi = await p.evaluate(() => window.__LOI_NOI || null);
  console.log(JSON.stringify({ loi, loiNoi, r, daNho }, null, 1));
  await b.close();

  const cuoiLaChuaGan = v => v.chinh[v.chinh.length-1] === '__none__';
  const hong = loi.length
    || !cuoiLaChuaGan(r.ten) || !cuoiLaChuaGan(r.unit) || !cuoiLaChuaGan(r.thieu)
    || r.ten.chinh[0] !== 'AEON MALL BÌNH DƯƠNG'
    || r.thieu.chinh[0] !== 'Cali Thảo Điền'
    || r.unit.chinh[0] !== 'AEON MALL TÂN PHÚ'      /* 50AMTP < 50BV175 < 50BVUB < 51AMBD */
    || r.unit.chinh[3] !== 'AEON MALL BÌNH DƯƠNG'
    || r.unit.chinh[4] !== 'Cali Thảo Điền'          /* o trong xuong cuoi */
    || r.ghe.chinh[0] !== 'AEON MALL TÂN PHÚ'        /* 27 ghe */
    || r.ghe.chinh[1] !== 'BV UNG BƯỚU'              /* 20 ghe */
    || r.sauKhiGo_giuNguyen.chinh[0] !== 'AEON MALL TÂN PHÚ'  /* go xong KHONG tu nhay */
    || r.sauKhiChonLai.chinh[0] !== 'Cali Thảo Điền'          /* chon lai -> 01AAA len dau */
    || daNho !== 'unit'
    || r.ten.rong.length !== 1 || r.ten.rong[0] !== 'ĐÀ NẴNG CENTER';
  process.exit(hong ? 1 : 0);
})();
