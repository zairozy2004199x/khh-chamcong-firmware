/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  13_DuLieuDauKy
 * ---------------------------------------------------------------------------
 * SỐ LIỆU MỘT LẦN, lấy từ file kế toán **`Tồn kho JP đến 31.7`** (Andy gửi
 * 04/08/2026). Hai thứ:
 *
 *   · `JP_HANG_31_7`     101 mã hàng — Mã MISA + tên
 *   · `JP_TON_31_7`      tồn cuối 31/07/2026 theo kho: 8 cơ sở + kho tổng,
 *                        25.527 cái · 614.132.522đ
 *
 * VÌ SAO ĐỂ TRONG CODE: hai bộ này trước đó phải import bằng **10 lần chọn
 * file** (1 danh mục + 9 kho). Đây là số liệu khai một lần trong đời hệ thống,
 * không phải dữ liệu chạy hàng ngày — để sẵn thì kế toán bấm 2 nút là xong, và
 * số nằm trong git nên đối chiếu lại được sau này.
 *
 * `unitCost` = giá trị ÷ số lượng của đúng dòng đó trong file, làm tròn về đồng.
 * Không phải giá bán — đây là **giá vốn**, dùng cho FIFO và sổ 632.
 *
 * ⚠️ Nút nạp tồn đầu kỳ **CHẶN CỨNG khi kho đã có phiếu tồn đầu kỳ**. Bấm hai
 * lần là tồn kho gấp đôi, mà tồn kho sai thì giá vốn 632 sai theo — không có
 * cách nào "hoàn" ngoài việc huỷ phiếu và sửa lớp tồn bằng tay.
 *═══════════════════════════════════════════════════════════════════════════*/

/** Ngày chốt của số liệu này. Tồn đầu kỳ của tháng 8 = tồn cuối 31/07. */
var JP_TON_NGAY = '2026-07-31';

/**
 * 103 mã hàng: `[mã, tên, ĐVT]` — hoặc `[mã, tên, ĐVT, GIÁ BÁN]` khi giá **không suy
 * ra được từ tiền tố mã**.
 *
 * Gần hết mã đều để ba phần tử: `jpGiaTuMa_` đọc tiền tố (`150JP041` → 150.000đ) nên
 * ghi lại giá ở đây là hai nguồn cho cùng một con số, sửa một chỗ quên chỗ kia.
 *
 * ⚠️ Phần tử thứ tư CHỈ dành cho mã mà tiền tố **không đọc được** — hiện đúng một mã,
 * `100-150JP113`, vì dấu gạch làm `^(\d+)\s*JP` không khớp. Andy chốt 11/08/2026:
 * **150.000đ**.
 *
 * ⚠️⚠️ **ĐỪNG sửa `jpGiaTuMa_` cho nó "hiểu" dấu gạch.** `100-150` là một KHOẢNG, máy
 * không biết lấy đầu nào — lấy số cuối thì hôm nay ra đúng 150k, nhưng mã kiểu
 * `200-100JPxxx` sau này sẽ ra sai mà không ai báo. Giá bán là số chảy thẳng vào doanh
 * thu; ở đây phải là con số kế toán ĐÃ NÓI, không phải con số máy đoán.
 */
var JP_HANG_31_7 = [
  ['100JP020', 'Trứng 6 Conan', 'Quả'],
  ['100JP021', 'Trứng gashapon Sanrio', 'Quả'],
  ['100JP023', 'Trứng shin siêu nhân', 'Quả'],
  ['100JP024', 'Trứng Naruto mới', 'Quả'],
  ['100JP025', 'Trứng Shin động vật dưới nước', 'Quả'],
  ['100JP026', 'Trứng Shin động vật trên cạn', 'Quả'],
  ['100JP027', 'Trứng hoạt hình cừu mới', 'Quả'],
  ['100JP028', 'Trứng Harry Potter', 'Quả'],
  ['100JP029', 'Trứng Kimetsu nhỏ', 'Quả'],
  ['100JP030', 'Trứng Kuromi', 'Quả'],
  ['100JP031', 'Trứng Doromon mới', 'Quả'],
  ['100JP032', 'Trứng Shin Pokemon', 'Quả'],
  ['100JP033', 'Trứng hoạt hình cao bồi', 'Quả'],
  ['100JP035', 'Trứng 6 siêu nhân Pikachu 100mm', 'Quả'],
  ['100JP038', 'Trứng Mickey 100mm', 'Quả'],
  ['100JP040', 'Trứng kem ốc quế pikachu', 'Quả'],
  ['100JP044', 'Trứng chuột hamster 75mm', 'Quả'],
  ['100JP045', 'Trứng móc khóa pokemon 65-75mm', 'Quả'],
  ['100JP046', 'Trứng NV hoạt hình đội mũ 120mm', 'Quả'],
  ['100JP049', 'Trứng Lân Sư tử', 'Quả'],
  ['100JP050', 'Trứng mèo thần tài', 'Quả'],
  ['100JP051', 'Trứng chuột lang', 'Quả'],
  ['100JP052', 'Trứng Mèo gõ', 'Quả'],
  ['100JP061', 'Trứng Na Tra', 'Quả'],
  ['100JP075', 'Trứng nhân vật InuYasha', 'Quả'],
  ['100JP090', 'Trứng chim cánh cụt 100mm', 'Quả'],
  ['100JP091', 'Trứng mèo ngồi tách trà', 'Quả'],
  ['100JP092', 'Trứng tôm và jerry to', 'Quả'],
  ['100JP093', 'Trứng hành tinh máy Ghashapon', 'Quả'],
  ['100JP094', 'Trứng rồng vàng nhỏ', 'Quả'],
  ['100JP095', 'Trứng doremon', 'Quả'],
  ['100JP096', 'Trứng marvel nhỏ', 'Quả'],
  ['100JP097', 'Trứng one pice lớn', 'Quả'],
  ['100JP098', 'Trứng kuromi cắm trại', 'Quả'],
  ['100JP099', 'Trứng cáo hồng', 'Quả'],
  ['100JP100', 'Trứng zombie hoa quả', 'Quả'],
  ['100JP111', 'Trứng Shin đổi mặt', 'Quả'],
  ['100JP112', 'Trứng khảo cổ', 'Quả'],
  ['100JP116', 'Trứng 6 mẫu stick', 'Quả'],
  ['100JP118', 'Trứng Ngựa', 'Bao'],
  ['100JP120', 'Zootopia V2 JP', 'Con'],
  ['100JP121', 'Trứng Capypara', 'Quả'],
  ['100JP122', 'Trứng chuột nước nhỏ', 'Quả'],
  ['100JP124', 'Trứng NV Jujutsu Kaisen JP', 'Cái'],
  ['100JP125', 'Trứng Bé khóc Crybaby JP', 'Cái'],
  ['100JP127', 'Trứng Phiên bản Q của đồ trang trí JP', 'Trái'],
  ['100JP128', 'Trứng Labubu hoạt hình', 'Quả'],
  ['100JP129', 'Trứng Tây du ký', 'Quả'],
  ['150JP057', 'Trứng nv hoạt hình+ đèn ngủ 100mm C-0316', 'Quả'],
  ['150JP059', 'Trứng gấu móc khóa 120mm', 'Quả'],
  ['150JP060', 'Trứng Lego Pokemon', 'Quả'],
  ['150JP065', 'Trứng ban nhạc trái cây', 'Quả'],
  ['150JP066', 'Trứng kem mới', 'Quả'],
  ['150JP067', 'Trứng gấu bóp to', 'Quả'],
  ['150JP068', 'Trứng Shin bóp', 'Quả'],
  ['150JP102', 'Trứng Yelodi', 'Quả'],
  ['150JP103', 'Trứng nhân vật Sanrio', 'Quả'],
  ['150JP104', 'Trứng NV hoạt hình tóc vàng dài', 'Quả'],
  ['150JP105', 'Trứng marvel cao', 'Quả'],
  ['150JP106', 'Trứng ngôi nhà shin chan', 'Quả'],
  ['150JP107', 'Trứng bọt biển (gia đình Sponge)', 'Quả'],
  ['150JP108', 'Trứng 8 nữ Chu Yin 100mm', 'Quả'],
  ['150JP109', 'Trứng ELK trượt tuyết', 'Quả'],
  ['150JP113', 'Mô hình Avatar JP', 'Con'],
  ['150JP114', 'Trứng Móc khóa linh vật ngựa JP', 'Cái'],
  ['150JP117', 'Trứng shin Stick', 'Quả'],
  ['150JP118', 'Trứng Vịt rồng', 'Quả'],
  ['150JP119', 'Trứng Lucky Capibara', 'Quả'],
  ['150JP120', 'Trứng kimetsu to', 'Quả'],
  ['150JP121', 'Trứng 6 mẫu công chúa cỡ lớn', 'Trái'],
  ['200JP064', 'Trứng 6 pokemon ngủ 100mm', 'Quả'],
  ['200JP071', 'Trứng con quay pokemon', 'Quả'],
  ['200JP073', 'Trứng chuỗi nhà hát Sanrio', 'Quả'],
  ['200JP074', 'Trứng Zombies', 'Quả'],
  ['200JP077', 'Trứng búp bê áo choàng', 'Quả'],
  ['200JP079', 'Trứng Búp bê naruto', 'Quả'],
  ['200JP080', 'Trứng búp bê nhồi bông', 'Con'],
  ['200JP112', 'Móc khóa gấu bông Zootopia JP', 'Con'],
  ['50JP003', 'Trứng mèo ninja 70mm', 'Quả'],
  ['50JP004', 'Trứng One pice', 'Quả'],
  ['50JP005', 'Trứng nhân vật hoạt hình nhỏ', 'Quả'],
  ['50JP007', 'Trứng kinh khí cầu vịt 70mm', 'Quả'],
  ['50JP008', 'Trứng hoạt hình nữ nhỏ', 'Quả'],
  ['50JP009', 'Trứng nấm quay', 'Quả'],
  ['50JP012', 'Trứng Shin hoa quả', 'Quả'],
  ['50JP017', 'Trứng Xe ô tô trượt nhỏ', 'Quả'],
  ['50JP018', 'Trứng gấu panda nhỏ', 'Quả'],
  ['50JP078', 'Trứng siêu nhân điện quang mini', 'Quả'],
  ['50JP079', 'Trứng biệt đội chó cứu hộ', 'Quả'],
  ['50JP080', 'Trứng siêu xe biến hình', 'Quả'],
  ['50JP081', 'Trứng đội xe vui vẻ (phương tiện giao thông)', 'Quả'],
  ['50JP082', 'Trứng siêu nhân ultraman tiga (sn đỏ)', 'Quả'],
  ['50JP083', 'Trứng LEGO', 'Quả'],
  ['50JP084', 'Trứng trực thăng', 'Quả'],
  ['50JP085', 'Trứng zombie nhỏ', 'Quả'],
  ['50JP086', 'Trứng nhân vật hoạt hình', 'Quả'],
  ['50JP087', 'Trứng thuyền', 'Quả'],
  ['50JP088', 'Trứng pikachu', 'Quả'],
  ['50JP089', 'Trứng 8 thỏ dễ thương', 'Quả'],
  ['50JP090', 'Trứng lộn xộn', 'Cái'],
  ['50JP100', 'Trứng NV Kimetsu JP', 'Cái'],
  ['50JP101', 'Trứng Móc khóa nón lá JP', 'Cái'],
  /* Giá bán 150.000đ — Andy chốt 11/08/2026. Tiền tố có dấu gạch nên `jpGiaTuMa_`
     không suy ra được; xem chú thích đầu bảng. */
  ['100-150JP113', 'Trứng pikachu trái cây', 'Quả', 150000]
];

/**
 * Tồn 31/07 theo kho. Khoá là **mã cơ sở** (`code` trong `JP_Locations`), riêng
 * `TONG` là kho tổng — trong file MISA nó tên `KHOMN` (Kho Miền Nam).
 *
 * Mỗi dòng: **[mã hàng, số lượng, GIÁ TRỊ]** — đúng hai con số file MISA có.
 * Đơn giá vốn KHÔNG lưu ở đây mà `jpNapTonDauKy31_7` chia ra: `giaTri / soLuong`.
 *
 * ⚠️ Vì sao không lưu sẵn đơn giá đã làm tròn: làm tròn về đồng từng dòng rồi nhân
 * lại với số lượng làm **lệch tới 1.689đ** ở kho tổng so với file. Giữ giá trị gốc
 * thì `soLuong × donGia` ra đúng số của file, không sai một đồng.
 *
 * ⚠️ **5 dòng có hàng mà giá trị = 0** — đúng như trong file MISA:
 * `150JP121` (386 ở SBPQ + 238 ở kho tổng) · `200JP080` (188 + 238) · `50JP090` (4).
 * Tổng 1.054 cái giá vốn 0đ, nên bán ra ghi 632 = 0. Đây là số của kế toán, không
 * phải lỗi đọc file — muốn khác thì sửa giá trong MISA rồi nạp lại.
 */
var JP_TON_31_7 = {
  /* Kho Miền Nam — 68 mã · 11367 cái · 207,098,278 đ */
  TONG: [
    ['100JP020', 13, 417382],
    ['100JP021', 29, 1032617],
    ['100JP023', 528, 4885187],
    ['100JP024', 14, 379361],
    ['100JP025', 733, 15122139],
    ['100JP026', 54, 2284186],
    ['100JP027', 17, 627081],
    ['100JP029', 7, 200924],
    ['100JP031', 405, 1990902],
    ['100JP033', 17, 595764],
    ['100JP035', 427, 931443],
    ['100JP040', 26, 794755],
    ['100JP045', 19, 831582],
    ['100JP046', 1150, 13999857],
    ['100JP049', 424, 2030131],
    ['100JP051', 2668, 91643895],
    ['100JP052', 765, 11300379],
    ['100JP075', 31, 902952],
    ['100JP090', 3, 130252],
    ['100JP091', 3, 90352],
    ['100JP092', 24, 672420],
    ['100JP093', 1, 46567],
    ['100JP094', 1, 35017],
    ['100JP095', 19, 632082],
    ['100JP097', 330, 95299],
    ['100JP098', 86, 2136000],
    ['100JP099', 14, 503545],
    ['100JP111', 689, 8897310],
    ['100JP120', 77, 2404972],
    ['100JP121', 6, 256001],
    ['100JP122', 75, 3287919],
    ['100JP125', 33, 585750],
    ['100JP128', 150, 1117262],
    ['150JP059', 12, 689012],
    ['150JP068', 13, 795175],
    ['150JP104', 39, 2228118],
    ['150JP107', 11, 665648],
    ['150JP108', 1, 52517],
    ['150JP114', 3, 152901],
    ['150JP118', 76, 3797516],
    ['150JP119', 129, 6544013],
    ['150JP120', 357, 4237765],
    ['150JP121', 238, 0],
    ['200JP073', 34, 3392219],
    ['200JP077', 68, 976564],
    ['200JP079', 510, 5802446],
    ['200JP080', 238, 0],
    ['200JP112', 595, 2798451],
    ['50JP003', 3, 44992],
    ['50JP004', 12, 254490],
    ['50JP005', 12, 288128],
    ['50JP007', 9, 170757],
    ['50JP008', 2, 48895],
    ['50JP009', 10, 168061],
    ['50JP017', 10, 179675],
    ['50JP018', 1, 24517],
    ['50JP078', 12, 189210],
    ['50JP079', 10, 164675],
    ['50JP080', 26, 455455],
    ['50JP081', 11, 154192],
    ['50JP082', 12, 285810],
    ['50JP083', 24, 595380],
    ['50JP084', 8, 89020],
    ['50JP085', 9, 141907],
    ['50JP086', 12, 276090],
    ['50JP087', 9, 93667],
    ['50JP088', 8, 252140],
    ['50JP089', 5, 227587]
  ],
  /* JP Sân bay Phú Quốc- Máy Tiền — 54 mã · 6885 cái · 167,682,413 đ */
  SBPQ: [
    /* ⚠️ Mã LẠ: `100-150JP113` có DẤU GẠCH nên `jpGiaTuMa_` suy ra giá = 0
       (regex đòi chữ số LIỀN TRƯỚC "JP"). Chính vì vậy bản nạp 04/08 bỏ sót
       nó — Andy gửi lại file 10/08 mới soi ra. Giá trị tồn trong file cũng là
       0đ nên TIỀN không lệch, chỉ thiếu 3 cái. Giữ nguyên 0đ theo file; đừng
       bịa giá vốn. Giá BÁN thì kế toán phải khai tay ở Danh mục hàng. */
    ['100-150JP113', 3, 0],
    ['100JP023', 255, 2359321],
    ['100JP026', 6, 205266],
    ['100JP027', 3, 110698],
    ['100JP028', 118, 4295690],
    ['100JP029', 18, 556167],
    ['100JP032', 1, 18279],
    ['100JP035', 294, 867519],
    ['100JP038', 5, 179764],
    ['100JP044', 7, 287052],
    ['100JP046', 573, 13940083],
    ['100JP049', 16, 745017],
    ['100JP050', 1, 31207],
    ['100JP051', 945, 44710382],
    ['100JP052', 121, 3447445],
    ['100JP061', 6, 265631],
    ['100JP075', 12, 349530],
    ['100JP096', 4, 107871],
    ['100JP097', 367, 403290],
    ['100JP099', 4, 143906],
    ['100JP100', 14, 522921],
    ['100JP111', 8, 305448],
    ['100JP112', 10, 706668],
    ['100JP116', 20, 704342],
    ['100JP118', 250, 8405835],
    ['100JP120', 3, 69750],
    ['100JP121', 240, 10221586],
    ['100JP124', 77, 860860],
    ['100JP128', 161, 5786883],
    ['100JP129', 217, 8296444],
    ['150JP057', 2, 132415],
    ['150JP060', 281, 2176682],
    ['150JP065', 562, 29845160],
    ['150JP066', 30, 1658288],
    ['150JP067', 2, 93747],
    ['150JP102', 40, 1907740],
    ['150JP103', 3, 132339],
    ['150JP105', 19, 920593],
    ['150JP117', 13, 626184],
    ['150JP118', 30, 1499020],
    ['150JP120', 251, 4153232],
    ['150JP121', 386, 0],
    ['200JP064', 729, 2953873],
    ['200JP071', 263, 390069],
    ['200JP073', 52, 5188100],
    ['200JP074', 44, 4553912],
    ['200JP077', 31, 445198],
    ['200JP079', 4, 257886],
    ['200JP080', 188, 0],
    ['200JP112', 166, 1220737],
    ['50JP008', 11, 268922],
    ['50JP012', 9, 215279],
    ['50JP090', 4, 0],
    ['50JP100', 5, 72414],
    ['50JP101', 4, 65768]
  ],
  /* JP Vinwonder Phú Quốc- Máy tiền — 20 mã · 2053 cái · 57,761,079 đ */
  VWPQ: [
    ['100JP023', 106, 998246],
    ['100JP031', 200, 983162],
    ['100JP046', 27, 981383],
    ['100JP049', 88, 1555484],
    ['100JP050', 14, 437370],
    ['100JP051', 483, 20650974],
    ['100JP052', 307, 7164777],
    ['100JP097', 3, 90816],
    ['100JP111', 108, 2311910],
    ['100JP112', 16, 1130668],
    ['100JP118', 176, 5917708],
    ['100JP120', 1, 31235],
    ['100JP121', 92, 3906139],
    ['100JP122', 83, 3638630],
    ['100JP128', 1, 34818],
    ['150JP065', 20, 954890],
    ['150JP105', 38, 1754897],
    ['150JP119', 71, 3601741],
    ['150JP120', 119, 1412588],
    ['200JP064', 100, 203643]
  ],
  /* JP Aeon Mall Tân Phú- Máy xu — 35 mã · 1785 cái · 65,426,162 đ */
  AMTP: [
    ['100JP023', 115, 3332801],
    ['100JP025', 78, 1647230],
    ['100JP026', 45, 1842642],
    ['100JP028', 48, 1619559],
    ['100JP035', 87, 2043760],
    ['100JP038', 67, 2408841],
    ['100JP046', 73, 2023300],
    ['100JP049', 73, 2129128],
    ['100JP050', 38, 1189661],
    ['100JP051', 71, 2935243],
    ['100JP052', 51, 1851718],
    ['100JP096', 5, 134462],
    ['100JP097', 66, 1182198],
    ['100JP098', 73, 2243242],
    ['100JP099', 26, 935157],
    ['100JP100', 12, 461851],
    ['100JP111', 50, 1928916],
    ['100JP116', 13, 460074],
    ['100JP120', 86, 2311580],
    ['100JP121', 74, 3145811],
    ['100JP127', 58, 942500],
    ['100JP128', 56, 1949728],
    ['150JP102', 36, 1722508],
    ['150JP105', 41, 1935294],
    ['150JP106', 28, 1628548],
    ['150JP109', 21, 1249867],
    ['150JP113', 48, 1600000],
    ['150JP118', 115, 5746239],
    ['150JP119', 44, 2232067],
    ['200JP071', 17, 1289731],
    ['200JP073', 24, 2268058],
    ['200JP074', 33, 2791580],
    ['200JP077', 45, 646256],
    ['200JP079', 57, 2895362],
    ['200JP112', 11, 701250]
  ],
  /* JP Sunworld Phú Quốc- Máy Tiền — 20 mã · 1074 cái · 34,512,939 đ */
  SWPQ: [
    ['100JP025', 59, 1344104],
    ['100JP026', 115, 4708974],
    ['100JP028', 82, 2881034],
    ['100JP031', 3, 121206],
    ['100JP035', 99, 864512],
    ['100JP038', 8, 287623],
    ['100JP046', 65, 1477321],
    ['100JP049', 80, 3152018],
    ['100JP050', 28, 873614],
    ['100JP051', 80, 3072722],
    ['100JP052', 45, 1803967],
    ['100JP099', 42, 1510637],
    ['100JP111', 38, 1056410],
    ['100JP116', 6, 211082],
    ['100JP121', 37, 1577429],
    ['100JP127', 1, 16250],
    ['100JP128', 75, 2148899],
    ['150JP118', 76, 3797514],
    ['200JP064', 100, 203643],
    ['200JP073', 35, 3403980]
  ],
  /* JP Aoen Mall Bình Dương - Máy Tiền — 21 mã · 1025 cái · 38,196,744 đ */
  AMBD: [
    ['100JP023', 65, 1130137],
    ['100JP025', 81, 3026383],
    ['100JP026', 71, 3003282],
    ['100JP028', 19, 641073],
    ['100JP032', 3, 101448],
    ['100JP035', 32, 989721],
    ['100JP046', 57, 1374433],
    ['100JP051', 64, 2569562],
    ['100JP052', 71, 3178935],
    ['100JP111', 51, 884896],
    ['100JP125', 43, 763250],
    ['100JP127', 2, 32500],
    ['100JP128', 75, 558631],
    ['150JP065', 89, 4754356],
    ['150JP105', 72, 3261117],
    ['150JP113', 38, 1266671],
    ['150JP119', 51, 2587168],
    ['200JP064', 21, 1367681],
    ['200JP073', 43, 4165159],
    ['200JP079', 71, 2143887],
    ['200JP112', 6, 396454]
  ],
  /* JP Aeon Mall Bình Tân- Máy Tiền — 10 mã · 543 cái · 14,337,289 đ */
  AMBT: [
    ['100JP025', 60, 1533310],
    ['100JP026', 70, 2960982],
    ['100JP031', 3, 121206],
    ['100JP035', 70, 973120],
    ['100JP046', 55, 1105503],
    ['100JP049', 64, 846489],
    ['100JP050', 9, 280365],
    ['100JP111', 58, 1114601],
    ['100JP120', 101, 3078252],
    ['100JP122', 53, 2323461]
  ],
  /* JP Vincom 3/2- Máy xu — 12 mã · 410 cái · 13,283,245 đ */
  VC32: [
    ['100JP023', 36, 794007],
    ['100JP025', 10, 384864],
    ['100JP026', 41, 1734290],
    ['100JP028', 36, 1263651],
    ['100JP033', 42, 1471887],
    ['100JP035', 45, 894134],
    ['100JP046', 28, 743823],
    ['100JP120', 40, 1063056],
    ['100JP122', 39, 1709718],
    ['100JP125', 29, 514750],
    ['100JP128', 33, 1228987],
    ['150JP065', 31, 1480078]
  ],
  /* JP SC VIVO- Máy xu — 16 mã · 385 cái · 15,834,373 đ */
  SCVV: [
    ['100JP025', 10, 305273],
    ['100JP027', 17, 627081],
    ['100JP028', 44, 1604331],
    ['100JP035', 6, 181210],
    ['100JP046', 14, 455785],
    ['100JP049', 17, 792421],
    ['100JP050', 5, 155985],
    ['100JP051', 7, 311053],
    ['100JP052', 3, 129942],
    ['100JP111', 18, 659039],
    ['100JP120', 62, 1754773],
    ['100JP121', 20, 851180],
    ['100JP125', 52, 923000],
    ['100JP127', 7, 113750],
    ['100JP128', 51, 1899343],
    ['200JP073', 52, 5070207]
  ]
};

/*════════════════════ NẠP DANH MỤC HÀNG ════════════════════*/

/**
 * Nạp 102 mã hàng từ file `Tồn kho JP đến 31.7`, thay cho việc import CSV.
 *
 * Đi qua đúng `jpCfgImportItems` chứ không tự ghi — hàm đó đã có sẵn phần suy giá
 * từ tiền tố mã, phần **không ghi khi không có gì đổi**, và audit. Viết đường ghi
 * thứ hai vào cùng một bảng là chắc chắn hai bản lệch nhau.
 */
function jpNapDanhMucHangJP(token) {
  jpNeedKT_(jpAuth_(token));
  /* a = [mã, tên, ĐVT] hoặc [mã, tên, ĐVT, GIÁ BÁN]. ĐVT lấy từ file MISA.
     `price = 0` là để `jpCfgImportItems` tự suy từ tiền tố mã (`jpGiaTuMa_`) — chỉ mã
     nào KHÔNG suy ra được mới khai tay phần tử thứ tư. Xem chú thích ở `JP_HANG_31_7`. */
  var rows = JP_HANG_31_7.map(function (a) {
    return { code: a[0], misa: a[0], name: a[1], dvt: a[2] || 'Quả',
             price: jpNum_(a[3]) };
  });
  var kq = jpCfgImportItems(token, rows);
  kq.msg = 'Danh mục hàng: thêm mới ' + kq.added + ' · cập nhật ' + kq.updated +
           (kq.khongDoi ? ' · ' + kq.khongDoi + ' mã đã đúng, không ghi lại' : '') +
           (kq.failed.length ? ' · ⚠ ' + kq.failed.length + ' mã KHÔNG suy ra được giá ' +
                               'từ mã hàng nên chưa vào danh mục: ' +
                               kq.failed.slice(0, 5).join(', ') +
                               ' — phải khai GIÁ BÁN tay ở Cấu hình → Danh mục hàng, ' +
                               'để trống là bán ra ghi doanh thu 0đ' : '');
  return kq;
}

/*════════════════════ NẠP BÙ PHẦN CÒN THIẾU ════════════════════*/

/**
 * NẠP BÙ theo CHÊNH LỆCH giữa bảng `JP_TON_31_7` và những gì ĐÃ KHAI.
 *
 * Andy 10/08/2026: *"mở đường nạp bù 3 cái đó đi em"*. Gửi lại file `Tồn kho JP đến
 * 31.7` và soi ra bản nạp 04/08 bỏ sót `100-150JP113` ở SBPQ — 3 cái, 0đ (xem mục
 * "SOÁT LẠI FILE" trong `CLAUDE.md`).
 *
 * `jpNapTonDauKy31_7` **chặn cứng** kho đã có phiếu `DAU_KY` — đúng, vì bấm hai lần
 * là tồn gấp đôi. Nhưng thế thì không còn đường nào bù phần thiếu. Hàm này lấp đúng
 * chỗ đó, và lấp bằng cách **so chênh lệch** chứ không "nạp lại":
 *
 * ⚠️⚠️ SO VỚI PHIẾU ĐÃ KHAI, **KHÔNG** so với lớp tồn còn lại. Lớp tồn bị BÁN trừ
 * dần, nên so với nó thì mọi mã đã bán đều trông như "thiếu" và hàm sẽ **nạp lại
 * đúng phần đã bán** — tồn phình lên, giá vốn 632 sai theo, và không dòng nào giải
 * thích. Đây là chỗ dễ làm sai nhất cả hàm.
 *
 * ⚠️ CHỈ bù phần THIẾU (chênh dương). Khai NHIỀU HƠN bảng (chênh âm) thì **nói ra,
 * không tự sửa** — trừ tồn tự động là xoá hàng có thật mà không ai yêu cầu.
 *
 * ⚠️ HAI BƯỚC: `ghi = false` chỉ đếm và in ra; `ghi = true` mới ghi. Ghi thẳng vào
 * sổ đã chốt thì không được một-nút-xong. Cùng khuôn với `jpDoiTkKhoCu`.
 *
 * Chạy lại an toàn: ghi xong thì chênh về 0 nên lượt sau không tìm thấy gì. Không
 * cần cờ "đã chạy".
 */
function jpNapBuTonDauKy31_7(token, ghi) {
  var u = jpNeedKT_(jpAuth_(token));

  var theoCode = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    var c = jpStr_(l.code).toUpperCase();
    if (c) theoCode[c] = l;
  });

  /* Phiếu ĐẦU KỲ chưa huỷ, theo kho — và chi tiết của chúng */
  var phieuCuaKho = {}, idPhieu = {};
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    if (jpLoaiNhap_(h) !== JP_NHAP_DAU_KY) return;
    var kho = jpStr_(h.khoId) || JP_KHO_TONG;
    (phieuCuaKho[kho] = phieuCuaKho[kho] || []).push(String(h.id));
    idPhieu[String(h.id)] = kho;
  });
  /* ĐÃ KHAI = Σ số lượng trên chi tiết phiếu đầu kỳ, theo (kho, mã) */
  var daKhai = {};
  jpRows_(JP_TABS.KHO_NHAP_CT).forEach(function (d) {
    var kho = idPhieu[String(d.nhapId)];
    if (!kho) return;
    var k = kho + '|' + jpStr_(d.itemCode);
    daKhai[k] = (daKhai[k] || 0) + jpNum_(d.qty);
  });

  var thieu = [], thua = [], khongThayCoSo = [], chuaKhaiKho = [];
  var soMa = 0, soCai = 0, soTien = 0;

  Object.keys(JP_TON_31_7).forEach(function (ma) {
    var khoId, tenKho;
    if (ma === JP_KHO_TONG) { khoId = JP_KHO_TONG; tenKho = 'KHO TỔNG'; }
    else {
      var l = theoCode[ma];
      if (!l) { khongThayCoSo.push(ma); return; }
      khoId = String(l.id); tenKho = jpStr_(l.name) || ma;
    }

    /* Kho CHƯA khai gì thì đây không phải việc của hàm này — dùng nút nạp cả lô.
       Bù vào kho trắng là làm lẫn hai đường, và mất luôn cảnh báo "đã có phiếu". */
    if (!(phieuCuaKho[khoId] || []).length) { chuaKhaiKho.push(tenKho); return; }

    var dong = [];
    JP_TON_31_7[ma].forEach(function (r) {
      var maHang = String(r[0]), can = jpNum_(r[1]), giaTri = jpNum_(r[2]);
      var co = jpNum_(daKhai[khoId + '|' + maHang]);
      var chenh = can - co;
      if (chenh === 0) return;
      if (chenh < 0) {
        thua.push({ kho: tenKho, ma: maHang, daKhai: co, bang: can, chenh: chenh });
        return;
      }
      /* Đơn giá = giá trị ÷ số lượng của BẢNG, y như `jpNapTonDauKy31_7` — đừng làm
         tròn rồi nhân lại, lệch với file MISA. Phần bù lấy đơn giá đó nhân số thiếu. */
      var donGia = can > 0 ? (giaTri / can) : 0;
      dong.push({ itemCode: maHang, qty: chenh, unitCost: donGia });
      thieu.push({ kho: tenKho, khoId: khoId, ma: maHang, daKhai: co, bang: can,
                   bu: chenh, donGia: donGia, tien: jpDong_(chenh * donGia) });
      soMa++; soCai += chenh; soTien += jpDong_(chenh * donGia);
    });

    if (ghi && dong.length) {
      var kq = jpKhoSoDuDauKy(token, {
        khoId: khoId, ngay: JP_TON_NGAY, rows: dong,
        ghiChu: 'NẠP BÙ tồn 31/07/2026 — phần còn thiếu so với file Tồn kho JP đến 31.7'
      });
      thieu.forEach(function (x) { if (x.khoId === khoId && !x.soChungTu) x.soChungTu = kq.soChungTu; });
    }
  });

  if (ghi) {
    jpAudit_(u, 'KHO_NAP_BU_TON_31_7', '', '',
             { soMa: soMa, soCai: soCai, soTien: soTien,
               thua: thua.length, chuaKhaiKho: chuaKhaiKho.length });
  }

  var msg;
  if (!soMa) {
    msg = 'Không thiếu mã nào — số đã khai khớp đủ với file Tồn kho JP đến 31.7';
  } else {
    msg = (ghi ? 'ĐÃ NẠP BÙ ' : 'Sẽ nạp bù ') + soMa + ' mã · ' + soCai + ' cái · ' +
          jpMoney_(soTien) + 'đ' + (ghi ? '' : ' — bấm "Ghi thật" để ghi');
  }
  /* ⚠️ Nói ra CẢ ba tình huống khác nhau, đừng gộp: thiếu thì bù được, thừa thì KHÔNG
     tự sửa, kho chưa khai thì phải dùng nút nạp cả lô. Gộp một câu là kế toán không
     biết mình phải làm gì tiếp. */
  if (thua.length) {
    msg += '\n⚠ ' + thua.length + ' mã ĐÃ KHAI NHIỀU HƠN bảng — KHÔNG tự sửa, ' +
           'trừ tồn tự động là xoá hàng có thật. Soát tay: ' +
           thua.slice(0, 5).map(function (x) {
             return x.kho + '/' + x.ma + ' (khai ' + x.daKhai + ' vs bảng ' + x.bang + ')';
           }).join(', ');
  }
  if (chuaKhaiKho.length) {
    msg += '\n· ' + chuaKhaiKho.length + ' kho CHƯA khai tồn đầu kỳ lần nào (' +
           chuaKhaiKho.slice(0, 5).join(', ') + ') — dùng nút "Nạp tồn 31/07 cho CẢ 9 kho"';
  }
  if (khongThayCoSo.length) {
    msg += '\n⚠ Không tìm thấy cơ sở cho mã kho: ' + khongThayCoSo.join(', ');
  }

  return { ok: true, ghi: !!ghi, soMa: soMa, soCai: soCai, soTien: soTien,
           thieu: thieu, thua: thua, chuaKhaiKho: chuaKhaiKho,
           khongThayCoSo: khongThayCoSo, msg: msg };
}

/*════════════════════ NẠP TỒN ĐẦU KỲ 31/07/2026 ════════════════════*/

/**
 * Khai tồn đầu kỳ cho kho tổng + 8 cơ sở bằng MỘT lần bấm, thay cho 9 lần chọn file.
 *
 * ⚠️ **CHẶN CỨNG kho đã có phiếu tồn đầu kỳ.** `jpKhoSoDuDauKy` chỉ *cảnh báo* khi
 * kho đã có phiếu `DAU_KY` — hợp lý khi kế toán tự khai từng đợt, nhưng ở đây là nạp
 * cả lô nên bấm hai lần là **tồn kho gấp đôi**, và tồn sai thì giá vốn 632 sai theo.
 * Kho nào đã có thì BỎ QUA và nói ra, không ghi thêm gì.
 *
 * Đi qua đúng `jpKhoSoDuDauKy` (tức là qua `jpGhiPhieuNhap_` với `loaiNhap = DAU_KY`)
 * nên tồn đầu kỳ **không sinh công nợ nhà cung cấp** — xem `JP_NHAP_LOAI` ở `00_Config`.
 */
function jpNapTonDauKy31_7(token) {
  var u = jpNeedKT_(jpAuth_(token));

  /* Mã cơ sở → id. Đọc một lần. Kho tổng không nằm trong danh mục cơ sở. */
  var theoCode = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
    var c = jpStr_(l.code).toUpperCase();
    if (c) theoCode[c] = l;
  });

  /* Kho nào ĐÃ có phiếu tồn đầu kỳ chưa huỷ */
  var daKhai = {};
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    if (jpLoaiNhap_(h) !== JP_NHAP_DAU_KY) return;
    daKhai[jpStr_(h.khoId) || JP_KHO_TONG] = jpStr_(h.soChungTu);
  });

  var xong = [], boQua = [], khongThayCoSo = [], khongCoGia = [];
  var tongSL = 0, tongTien = 0;

  Object.keys(JP_TON_31_7).forEach(function (ma) {
    var khoId, tenKho;
    if (ma === JP_KHO_TONG) {
      khoId = JP_KHO_TONG; tenKho = 'KHO TỔNG';
    } else {
      var l = theoCode[ma];
      if (!l) { khongThayCoSo.push(ma); return; }
      khoId = String(l.id); tenKho = jpStr_(l.name) || ma;
    }

    if (daKhai[khoId]) {
      boQua.push(tenKho + ' (đã có ' + daKhai[khoId] + ')');
      return;
    }

    /* Đơn giá vốn = giá trị ÷ số lượng, chia ở đây chứ không lưu sẵn số đã làm tròn —
       xem chú thích ở `JP_TON_31_7`. Dòng giá trị 0 thì đơn giá 0, đúng như file. */
    var soKhongGia = 0;
    var rows = JP_TON_31_7[ma].map(function (r) {
      if (!r[2]) soKhongGia++;
      return { itemCode: r[0], qty: r[1], unitCost: r[2] / r[1] };
    });
    if (soKhongGia) khongCoGia.push(tenKho + ': ' + soKhongGia + ' mã');
    var kq = jpKhoSoDuDauKy(token, {
      khoId: khoId, ngay: JP_TON_NGAY, rows: rows,
      ghiChu: 'Tồn 31/07/2026 — nạp từ file Tồn kho JP đến 31.7'
    });
    var sl = rows.reduce(function (s, r) { return s + r.qty; }, 0);
    tongSL += sl; tongTien += jpNum_(kq.tongTien);
    xong.push({ kho: tenKho, soChungTu: kq.soChungTu, soDong: kq.soDong,
                soLuong: sl, tongTien: jpNum_(kq.tongTien) });
  });

  jpAudit_(u, 'KHO_NAP_TON_31_7', '', '',
           { xong: xong.length, boQua: boQua.length,
             khongThayCoSo: khongThayCoSo.length, tongSL: tongSL, tongTien: tongTien });

  var msg = xong.length
    ? 'Đã khai tồn đầu kỳ cho ' + xong.length + ' kho · ' + tongSL + ' cái · ' +
      jpMoney_(tongTien) + 'đ'
    : 'Không khai thêm kho nào';
  if (boQua.length) {
    msg += '\n· BỎ QUA ' + boQua.length + ' kho đã có phiếu tồn đầu kỳ: ' +
           boQua.join(', ') + '\n  (bấm hai lần là tồn gấp đôi nên hàm này chặn cứng —' +
           ' muốn khai lại thì huỷ phiếu cũ trước)';
  }
  if (khongThayCoSo.length) {
    msg += '\n⚠ Không tìm thấy cơ sở mã: ' + khongThayCoSo.join(', ') +
           ' — bấm "Nạp / cập nhật 13 cơ sở" ở Cấu hình → Cơ sở trước';
  }
  if (khongCoGia.length) {
    msg += '\n⚠ Có mã tồn mà GIÁ TRỊ = 0 trong file MISA (' + khongCoGia.join(' · ') +
           ') — lớp tồn đó giá vốn 0đ nên bán ra ghi 632 = 0. Sửa giá trong MISA rồi ' +
           'huỷ phiếu và nạp lại nếu cần.';
  }
  return { ok: true, xong: xong, boQua: boQua, khongThayCoSo: khongThayCoSo,
           khongCoGia: khongCoGia, tongSL: tongSL, tongTien: tongTien, msg: msg };
}

/*════════════════════ DỰNG HỆ THỐNG MỘT PHÁT ════════════════════*/

/**
 * Chạy TOÀN BỘ các bước dựng hệ thống bằng một lần bấm, đúng thứ tự bắt buộc:
 *
 *   ① nạp 13 cơ sở + loại máy       (phải trước ④ — PIN lấy loại máy theo cơ sở)
 *   ② ngưng cơ sở DEMO còn sót      (phải trước ④ — kẻo cấp PIN cho cơ sở rác)
 *   ③ nạp 102 mã hàng
 *   ④ cấp tài khoản + PIN theo cơ sở
 *   ⑤ khai tồn đầu kỳ 31/07 cho 9 kho   (phải sau ① — cần id cơ sở)
 *
 * Không viết lại logic nào — gọi đúng năm hàm đã có, mỗi hàm đã tự chốt an toàn:
 * nạp cơ sở khớp theo mã nên chạy lại không tạo trùng · danh mục hàng không ghi khi
 * không có gì đổi · cấp PIN bỏ qua cơ sở đã có tài khoản (trừ khi `capLai`) · khai tồn
 * CHẶN CỨNG kho đã có phiếu đầu kỳ. Nên bấm hai lần **không** nhân đôi gì.
 *
 * ⚠️ Bản rõ của PIN trả về ĐÚNG MỘT LẦN trong `pin.rows` — giao diện phải cho tải CSV
 * ngay. Không ghi vào sheet, không vào `jpAudit_`.
 */
function jpDungHeThongMotPhat(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var buoc = [];

  /* ── ① Cơ sở ── */
  var msgCoSo = jpNapCoSoJP_();
  buoc.push({ ten: '① Nạp 13 cơ sở + loại máy', chiTiet: msgCoSo });

  /* ── ② Ngưng cơ sở DEMO còn sót ──
     Dấu hiệu CHẶT: không có `unitCode` **và** `maKH` dạng `KH-xxx` — đúng chữ ký của
     `jpSeedDemo_`. Cơ sở thật dùng `KH00xxx`, cơ sở mới kế toán tự thêm thì thường để
     trống `maKH`. KHÔNG dò theo "có nằm trong `JP_CO_SO_2026` hay không": kế toán mở
     điểm mới là cơ sở đó chưa nằm trong bảng cứng, ngưng nó đi là chặn oan cả điểm. */
  var daNgung = [], tkDaNgung = [];
  var locs = jpRows_(JP_TABS.LOCATIONS);
  locs.forEach(function (l) {
    if (jpStr_(l.active) === 'N') return;
    if (jpStr_(l.unitCode)) return;
    if (!/^KH-/.test(jpStr_(l.maKH))) return;
    jpFields_(JP_TABS.LOCATIONS, l._row, { active: 'N' });
    daNgung.push(jpStr_(l.code) + ' — ' + jpStr_(l.name));
  });

  /* Tài khoản cơ sở chỉ trỏ vào cơ sở vừa ngưng thì ngưng luôn — để nó sống là còn
     một PIN đăng nhập được mà không mở được báo cáo nào. */
  if (daNgung.length) {
    var conChay = {};
    jpRows_(JP_TABS.LOCATIONS).forEach(function (l) {
      if (jpStr_(l.active) !== 'N') conChay[String(l.id)] = 1;
    });
    jpRows_(JP_TABS.USERS).forEach(function (r) {
      if (jpStr_(r.active) === 'N') return;
      if (!/^cs/.test(jpStr_(r.username))) return;
      var ids = jpStr_(r.locationIds).split(',').filter(function (x) { return x; });
      if (!ids.length) return;
      var conSong = ids.filter(function (id) { return conChay[id]; });
      if (conSong.length) return;
      jpFields_(JP_TABS.USERS, r._row, { active: 'N' });
      tkDaNgung.push(jpStr_(r.username));
    });
  }
  buoc.push({ ten: '② Ngưng cơ sở demo còn sót',
    chiTiet: daNgung.length
      ? 'Đã ngưng ' + daNgung.length + ' cơ sở: ' + daNgung.join(' · ') +
        (tkDaNgung.length ? '\nNgưng luôn tài khoản chỉ thuộc cơ sở đó: ' +
                            tkDaNgung.join(', ') : '')
      : 'Không có cơ sở demo nào (không cơ sở nào vừa thiếu mã đơn vị vừa có mã KH dạng KH-…)' });

  /* ── ③ Danh mục hàng ── */
  var hang = jpNapDanhMucHangJP(token);
  buoc.push({ ten: '③ Nạp 102 mã hàng', chiTiet: hang.msg });

  /* ── ④ PIN theo cơ sở ── */
  var pin = jpTaoPinCoSo(token, { capLai: p.capLai === true });
  buoc.push({ ten: '④ Cấp tài khoản + PIN theo cơ sở', chiTiet: pin.msg });

  /* ── ⑤ Tồn đầu kỳ ── */
  var ton = jpNapTonDauKy31_7(token);
  buoc.push({ ten: '⑤ Khai tồn đầu kỳ 31/07', chiTiet: ton.msg });

  jpAudit_(u, 'DUNG_HE_THONG', '', '',
           { coSoNgung: daNgung.length, tkNgung: tkDaNgung.length,
             hangThem: hang.added, pinCap: pin.rows.length,
             khoKhaiTon: ton.xong.length, capLai: p.capLai === true });

  return {
    ok: true, buoc: buoc,
    /* Bản rõ PIN — giao diện PHẢI cho tải CSV ngay, mất là cấp lại chứ không tra được */
    pin: pin,
    ton: ton,
    canhBao: pin.rows.length
      ? 'Danh sách PIN bên dưới chỉ hiện MỘT LẦN này. Tải CSV hoặc in trước khi rời màn hình.'
      : ''
  };
}

