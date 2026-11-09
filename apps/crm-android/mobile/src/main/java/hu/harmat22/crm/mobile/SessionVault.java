package hu.harmat22.crm.mobile;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.Build;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;

import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

/** Only encrypted auth cookies persist; the key requires a biometric on every operation. */
public final class SessionVault {
    private final SharedPreferences preferences;
    private final String alias;

    public SessionVault(Context context, boolean fixture) {
        String suffix = BuildConfig.DEBUG && fixture ? "_fixture" : "";
        preferences = context.getSharedPreferences("session_v1" + suffix, Context.MODE_PRIVATE);
        alias = "harmat_crm_biometric_v1" + suffix;
    }

    public boolean exists() { return preferences.contains("ciphertext") && preferences.contains("iv"); }

    public Cipher encryptionCipher() throws Exception {
        KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
        KeyGenParameterSpec.Builder spec = new KeyGenParameterSpec.Builder(alias,
            KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setKeySize(256).setUserAuthenticationRequired(true)
            .setInvalidatedByBiometricEnrollment(true);
        if (Build.VERSION.SDK_INT >= 30) spec.setUserAuthenticationParameters(0, KeyProperties.AUTH_BIOMETRIC_STRONG);
        else spec.setUserAuthenticationValidityDurationSeconds(-1);
        generator.init(spec.build());
        SecretKey key = generator.generateKey();
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, key);
        return cipher;
    }

    public Cipher decryptionCipher() throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore");
        store.load(null);
        SecretKey key = (SecretKey) store.getKey(alias, null);
        if (key == null) throw new IllegalStateException("Session key missing");
        byte[] iv = Base64.decode(preferences.getString("iv", ""), Base64.NO_WRAP);
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, key, new GCMParameterSpec(128, iv));
        return cipher;
    }

    public void save(Cipher authenticatedCipher, String cookie) throws Exception {
        String valid = PortalPolicy.authCookies(cookie);
        if (valid.isEmpty()) throw new IllegalArgumentException("Missing authenticated session");
        byte[] encrypted = authenticatedCipher.doFinal(valid.getBytes(StandardCharsets.UTF_8));
        if (!preferences.edit().putString("ciphertext", Base64.encodeToString(encrypted, Base64.NO_WRAP))
            .putString("iv", Base64.encodeToString(authenticatedCipher.getIV(), Base64.NO_WRAP)).commit()) {
            throw new IllegalStateException("Cannot save session");
        }
    }

    public String unlock(Cipher authenticatedCipher) throws Exception {
        byte[] encrypted = Base64.decode(preferences.getString("ciphertext", ""), Base64.NO_WRAP);
        String cookie = new String(authenticatedCipher.doFinal(encrypted), StandardCharsets.UTF_8);
        if (PortalPolicy.authCookies(cookie).isEmpty()) throw new IllegalStateException("Invalid session");
        return cookie;
    }

    @android.annotation.SuppressLint("ApplySharedPref") // Removal must finish before another unlock can start.
    public void clear() {
        preferences.edit().clear().commit();
        try {
            KeyStore store = KeyStore.getInstance("AndroidKeyStore");
            store.load(null);
            store.deleteEntry(alias);
        } catch (Exception ignored) { /* Missing/inaccessible keys never unlock data. */ }
    }
}
