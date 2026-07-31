// ============================================================================
//  secrets.h dùng cho GitHub Actions — TOÀN PLACEHOLDER, CỐ Ý.
//  ----------------------------------------------------------------------------
//  File .bin do CI build ra được đặt ở chỗ tải công khai (để bấm cập nhật từ xa),
//  nên nó KHÔNG ĐƯỢC chứa bí mật nào. Máy lấy bí mật từ bộ nhớ trong (NVS) —
//  xem khối "BÍ MẬT + 2 LINK" trong .ino. OTA không ghi đè NVS.
//
//  ⚠️ ĐỪNG điền giá trị thật vào file này. Điền là bí mật lên GitHub và lên
//     file .bin công khai.
// ============================================================================
#pragma once
#define SEC_WIFI_SSID     "__CHUA_CAU_HINH__"
#define SEC_WIFI_PASS     "__CHUA_CAU_HINH__"
#define SEC_AP_PASS       "__CHUA_CAU_HINH__"
#define SEC_HIK_USER      "__CHUA_CAU_HINH__"
#define SEC_HIK_PASS      "__CHUA_CAU_HINH__"
#define SEC_OTA_USER      "__CHUA_CAU_HINH__"
#define SEC_OTA_PASS      "__CHUA_CAU_HINH__"
#define SEC_EMP_TOKEN     "__CHUA_CAU_HINH__"
#define SEC_FB_SECRET     "__CHUA_CAU_HINH__"
// 2 link cũng phải placeholder: bản .bin công khai không được lộ link web app / Firebase.
// Máy đã di trú thì lấy từ NVS; chip trắng thì khai ở portal 192.168.4.1.
#define SEC_EXEC_URL      "__CHUA_CAU_HINH__"
#define SEC_FB_HOST       "__CHUA_CAU_HINH__"
