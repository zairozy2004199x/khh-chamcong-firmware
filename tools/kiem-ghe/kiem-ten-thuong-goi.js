const { chromium } = require('playwright');
/* Dung ghe trong anh anh Thang gui: ma 80096, ten sao ke CVST-8, o BV UNG BUOU. */
const MAY = [
  { ma:'80096', ten:'CVST-8',  ten_goi:'Ghế cạnh thang máy', coso:'BV UNG BƯỚU' },
  { ma:'80097', ten:'CVST-9',  ten_goi:'',                    coso:'BV UNG BƯỚU' },
  { ma:'80098', ten:'CVST-10', ten_goi:'Ghế sát cửa ra',      coso:'BV UNG BƯỚU' }
];
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1500, height:900 } });
  const loi = []; p.on('pageerror', e => loi.push(String(e)));
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(200);
  await p.evaluate((MAY) => {
    window.__GUI = [];
    window.__TRALOI['may_ten']     = (q) => { window.__GUI.push({ viec:'may_ten', d:q }); return { ok:true, thong_bao:'x' }; };
    window.__TRALOI['may_ten_goi'] = (q) => { window.__GUI.push({ viec:'may_ten_goi', d:q }); return { ok:true, thong_bao:'x' }; };
    window.__TRALOI['kt_ma_misa_map'] = { ok:true, map:{} };
    const D = { coso:[{id:1,ten:'BV UNG BƯỚU',tinh:'HCM',ma_kh:'',dong_cua:0}], may:MAY,
      tong:{tong:0,theo_coso:[]}, ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] };
    window.__TRALOI['so_lieu'] = D;
    window.__T.setTOK('t'); window.__T.setTAB('quan-ly'); window.__T.setD(D);
    document.getElementById('app').innerHTML = '<div id="ql-wrap"></div>';
    window.__T.qlGheRender();
  }, MAY);
  await p.waitForTimeout(350);

  const r = await p.evaluate(() => {
    const th = [].slice.call(document.querySelectorAll('#ql-wrap table tr:first-child th')).map(t => t.textContent.trim());
    const o = document.querySelectorAll('[data-tengoi]');
    const gt = {}; [].forEach.call(o, i => gt[i.getAttribute('data-tengoi')] = i.value);
    return { tieuDe: th, soOTenGoi: o.length, giaTri: gt,
             soOTen: document.querySelectorAll('[data-ten]').length };
  });

  /* Ve lai man SACH truoc moi thao tac: `lam()` goi `tai()` -> `ve()` ve lai CA trang bang du
     lieu gia, xoa mat #ql-wrap. Bai hoc y het phep kiem co so trung. */
  async function veLai(){
    await p.evaluate((MAY) => {
      document.getElementById('app').innerHTML = '<div id="ql-wrap"></div>';
      window.__T.qlGheRender();
    }, MAY);
    await p.waitForTimeout(200);
  }

  // Sua ten thuong goi -> phai di duong RIENG, khong dung ten sao ke
  await p.fill('[data-tengoi="80097"]', 'Ghế góc trong');
  await p.evaluate(() => document.querySelector('[data-tengoi="80097"]').blur());
  await p.waitForTimeout(300);
  // Sua ten sao ke -> van di duong cu
  await veLai();
  await p.fill('[data-ten="80097"]', 'CVST-9B');
  await p.evaluate(() => document.querySelector('[data-ten="80097"]').blur());
  await p.waitForTimeout(300);
  const gui = await p.evaluate(() => window.__GUI);

  await veLai();
  await p.screenshot({ path:'/tmp/ghetest/tengoi.png', fullPage:false });
  console.log(JSON.stringify({ loi, r, gui }, null, 1));
  await b.close();

  const hong = loi.length
    || r.tieuDe.indexOf('Ten thuong goi') < 0
    || r.soOTenGoi !== 3 || r.soOTen !== 3
    || r.giaTri['80096'] !== 'Ghế cạnh thang máy'
    || r.giaTri['80097'] !== ''
    || gui.length !== 2
    || gui[0].viec !== 'may_ten_goi' || gui[0].d.ten_goi !== 'Ghế góc trong' || gui[0].d.ma !== '80097'
    || gui[1].viec !== 'may_ten'     || gui[1].d.ten !== 'CVST-9B'
    || ('ten_goi' in gui[1].d)       /* duong ten sao ke KHONG duoc mang ten thuong goi */
    || ('ten' in gui[0].d);          /* va nguoc lai */
  process.exit(hong ? 1 : 0);
})();
