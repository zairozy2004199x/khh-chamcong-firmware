const { chromium } = require('playwright');
/* Dung ten trong anh anh Thang gui — co dau, co ngoac, co chu HOA lan thuong. */
const BCP = [
  { pin:'433957', ten:'Đặng thị ngọc châu', coso:'VINCOM GRAND PARK', ghe:'', active:1 },
  { pin:'782006', ten:'Đinh Bùi Xuân Chiến', coso:'KUBO GO BIÊN HÒA', ghe:'', active:1 },
  { pin:'230519', ten:'DƯƠNG TRUNG TÍN', coso:'COOPMART PHÚ LÂM; GALAXY KINH DƯƠNG VƯƠNG; SC VIVO', ghe:'', active:1 },
  { pin:'688593', ten:'Hà Quang Thắng', coso:'GO BUÔN MÊ THUỘT; YOKID BUÔN MÊ THUỘT', ghe:'', active:1 },
  { pin:'594619', ten:'Hòa ( Bến Tre )', coso:'GO BẾN TRE; GO MỸ THO', ghe:'', active:1 },
  { pin:'120303', ten:'Huỳnh Ngọc Thanh', coso:'CGV BÌNH DƯƠNG; GO THỦ DẦU MỘT', ghe:'', active:1 }
];
(async () => {
  const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport:{ width:1400, height:900 } });
  const loi = []; p.on('pageerror', e => loi.push(String(e)));
  await p.goto('file:///tmp/ghetest/trang.html'); await p.waitForTimeout(200);
  await p.evaluate((BCP) => {
    window.__T.setTOK('t');
    window.__T.setD({ coso:[{id:1,ten:'GO BẾN TRE',tinh:'Bến Tre',ma_kh:'',dong_cua:0}], may:[], tong:{tong:0,theo_coso:[]},
      ai:{name:'K',role:'admin'}, cho:[], choGan:[], loi:[], nhat_ky:[] });
    window.__T.setBCP(BCP, []);
    document.getElementById('app').innerHTML = window.__T.veBcPin();
    window.__T.noiBcPin();
  }, BCP);
  await p.waitForTimeout(300);

  async function go(q){
    await p.fill('#bcp-tim', q);
    await p.waitForTimeout(120);
    return p.evaluate(() => ({
      hien: [].slice.call(document.querySelectorAll('#bcp-bang tr[data-bcptim]'))
        .filter(tr => tr.style.display !== 'none')
        .map(tr => tr.cells[1].textContent.trim()),
      dem: (document.getElementById('bcp-dem-tim')||{}).textContent || ''
    }));
  }

  const r = {};
  r.rong      = await go('');
  r.khongDau  = await go('thang');      /* -> Hà Quang Thắng */
  r.coDau     = await go('Thắng');
  r.hoaThuong = await go('DUONG');      /* -> DƯƠNG TRUNG TÍN + GALAXY KINH DƯƠNG VƯƠNG + BINH DUONG */
  r.theoPin   = await go('594619');
  r.theoCoso  = await go('ben tre');
  r.khongCo   = await go('xyzkhongcoai');
  r.chau      = await go('chau');        /* dau 'â' */

  await p.fill('#bcp-tim', 'thang'); await p.waitForTimeout(120);
  await p.screenshot({ path:'/tmp/ghetest/timnv.png', fullPage:false });
  console.log(JSON.stringify({ loi, r }, null, 1));
  await b.close();

  const hong = loi.length
    || r.rong.hien.length !== 6 || r.rong.dem.indexOf('6') < 0
    || r.khongDau.hien.join() !== 'Hà Quang Thắng'      /* go khong dau van ra */
    || r.coDau.hien.join()    !== 'Hà Quang Thắng'      /* go co dau cung ra */
    || r.theoPin.hien.join()  !== 'Hòa ( Bến Tre )'
    /* Chi MOT nguoi khop "ben tre" (Hoa — ca ten lan co so). Ban dau em doan 2 va bao hong OAN:
       phep kiem phai dem tu du lieu thu, khong doan bang cam tinh. */
    || r.theoCoso.hien.join() !== 'Hòa ( Bến Tre )'
    || r.khongCo.hien.length !== 0 || r.khongCo.dem.indexOf('không thấy') < 0
    || r.chau.hien.join()     !== 'Đặng thị ngọc châu'
    || r.hoaThuong.hien.length < 2;
  process.exit(hong ? 1 : 0);
})();
