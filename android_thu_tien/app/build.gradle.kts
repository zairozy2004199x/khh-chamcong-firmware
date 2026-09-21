plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "vn.khh.ghe"
    compileSdk = 34

    defaultConfig {
        applicationId = "vn.khh.ghe"
        /* minSdk 24 = Android 7.0. Điện thoại nhân viên dùng để đi thu tiền thường là máy cũ;
           đặt 26 là loại luôn một phần máy đang có sẵn trong tay người ta. */
        minSdk = 24
        targetSdk = 34
        versionCode = 2
        versionName = "1.0.1"
        vectorDrawables { useSupportLibrary = true }
    }

    buildTypes {
        release {
            /* 🔴 KHÔNG rút gọn mã ở bản đầu. R8 cắt nhầm một lớp của ML Kit hay OkHttp thì app
               chạy được trên máy mình mà nổ trên máy nhân viên — và đúng lúc họ đang đứng cạnh
               một ngăn tiền đã mở. Bật lại khi đã có bộ luật giữ lớp được kiểm thật. */
            isMinifyEnabled = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
    buildFeatures { compose = true }
    composeOptions { kotlinCompilerExtensionVersion = "1.5.14" }
    packaging {
        resources { excludes += "/META-INF/{AL2.0,LGPL2.1}" }
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.4")
    implementation("androidx.activity:activity-compose:1.9.1")

    val compose = platform("androidx.compose:compose-bom:2024.06.00")
    implementation(compose)
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-graphics")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.compose.material:material-icons-extended")

    /* Camera + đọc mã QR. Bản `barcode-scanning` gói sẵn mô hình vào APK: quét được NGAY LÚC
       KHÔNG CÓ MẠNG. Bản `barcode-scanning-common` phải tải mô hình về lần đầu — mà lần đầu
       chính là lúc nhân viên đứng ở kho hàng sóng yếu. */
    implementation("androidx.camera:camera-core:1.3.4")
    implementation("androidx.camera:camera-camera2:1.3.4")
    implementation("androidx.camera:camera-lifecycle:1.3.4")
    implementation("androidx.camera:camera-view:1.3.4")
    implementation("com.google.mlkit:barcode-scanning:17.3.0")

    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("androidx.work:work-runtime-ktx:2.9.1")
}
