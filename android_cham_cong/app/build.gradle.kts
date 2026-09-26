plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "vn.khh.chamcong"
    compileSdk = 34

    defaultConfig {
        applicationId = "vn.khh.chamcong"
        /* minSdk 24 = Android 7.0 — cùng mức với app thu tiền, và cùng lý do: điện thoại nhân
           viên cửa hàng thường là máy cũ. Đặt cao hơn là loại luôn một phần máy đang có sẵn
           trong tay người ta, để đổi lấy mấy hàm mà app này không dùng tới. */
        minSdk = 24
        targetSdk = 34
        versionCode = 4
        versionName = "1.3.0"
        vectorDrawables { useSupportLibrary = true }
    }

    buildTypes {
        release {
            /* Cùng lý do với app thu tiền: R8 cắt nhầm thì app nổ trên máy nhân viên chứ không
               nổ trên máy mình. App này mỏng, rút gọn cũng không tiết kiệm được bao nhiêu. */
            isMinifyEnabled = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

/* 🔴 CỐ Ý KHÔNG DÙNG COMPOSE, VÀ KHÔNG THÊM THƯ VIỆN NÀO NGOÀI CÁI TỐI THIỂU.
   Toàn bộ giao diện của app này là một `WebView` phủ kín màn, cộng hai màn phụ (nhập địa chỉ
   máy chủ, và báo mất mạng) mỗi cái vài nút. Kéo cả Compose vào để vẽ hai màn ấy là thêm vài
   MB và một tầng phụ thuộc phải nâng cấp mãi — đổi lấy đúng không gì cả.
   Nhẹ còn có nghĩa thật: máy cũ của nhân viên cửa hàng thường đã đầy bộ nhớ. */
dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    /* Lịch chạy nền để hỏi lời nhắc. Đây là thứ duy nhất kéo thêm ngoài AppCompat — và nó thay
       cho Web Push, thứ KHÔNG chạy trong WebView. */
    implementation("androidx.work:work-runtime-ktx:2.9.1")
}
