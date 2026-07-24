import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("keystore.properties")
val hasReleaseSigning = keystorePropertiesFile.exists()
val firebaseProperties = Properties()
val firebasePropertiesFile = rootProject.file("firebase.properties")

if (firebasePropertiesFile.exists()) {
    firebasePropertiesFile.inputStream().use { stream ->
        firebaseProperties.load(stream)
    }
}

fun quotedBuildConfigValue(value: String): String {
    return "\"${value.replace("\\", "\\\\").replace("\"", "\\\"")}\""
}

if (hasReleaseSigning) {
    keystorePropertiesFile.inputStream().use { stream ->
        keystoreProperties.load(stream)
    }
}

android {
    namespace = "com.appswebnetkz.crm369"
    compileSdk = 35

    signingConfigs {
        if (hasReleaseSigning) {
            create("release") {
                storeFile = rootProject.file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    defaultConfig {
        applicationId = "com.appswebnetkz.crm369.android"
        minSdk = 26
        targetSdk = 35
        versionCode = 2
        versionName = "2.0.0"

        buildConfigField(
            "String",
            "FIREBASE_API_KEY",
            quotedBuildConfigValue(firebaseProperties.getProperty("apiKey", "")),
        )
        buildConfigField(
            "String",
            "FIREBASE_APP_ID",
            quotedBuildConfigValue(firebaseProperties.getProperty("appId", "")),
        )
        buildConfigField(
            "String",
            "FIREBASE_PROJECT_ID",
            quotedBuildConfigValue(firebaseProperties.getProperty("projectId", "")),
        )
        buildConfigField(
            "String",
            "FIREBASE_SENDER_ID",
            quotedBuildConfigValue(firebaseProperties.getProperty("senderId", "")),
        )

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            if (hasReleaseSigning) {
                signingConfig = signingConfigs.getByName("release")
            }
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        buildConfig = true
    }
}

tasks.configureEach {
    if (name == "assembleRelease" || name == "bundleRelease") {
        doFirst {
            check(hasReleaseSigning) {
                "Release signing is not configured. Create applicationsOfMobileAndroid/keystore.properties and provide storeFile, storePassword, keyAlias, and keyPassword."
            }
        }
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.15.0")
    implementation("androidx.core:core-splashscreen:1.0.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("androidx.activity:activity-ktx:1.10.1")
    implementation(platform("com.google.firebase:firebase-bom:34.16.0"))
    implementation("com.google.firebase:firebase-messaging")
    implementation("com.google.android.gms:play-services-code-scanner:16.1.0")

    testImplementation("junit:junit:4.13.2")
    testImplementation("org.json:json:20240303")
    androidTestImplementation("androidx.test.ext:junit:1.2.1")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.6.1")
}
