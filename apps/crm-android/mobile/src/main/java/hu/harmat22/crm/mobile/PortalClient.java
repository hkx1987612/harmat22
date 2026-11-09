package hu.harmat22.crm.mobile;

import android.content.Context;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.nio.charset.StandardCharsets;

public final class PortalClient {
    private static final int MAX_BYTES = 8 * 1024 * 1024;
    private final Context context;
    private final boolean fixture;
    private volatile HttpURLConnection active;

    public PortalClient(Context context, boolean fixture) {
        this.context = context.getApplicationContext();
        this.fixture = BuildConfig.DEBUG && fixture;
    }

    public PortalPage read(String view, String language, String cookies) throws Exception {
        if (Thread.currentThread().isInterrupted()) throw new IOException("Cancelled");
        java.net.URI uri = PortalPolicy.readUri(view, language);
        if (fixture) {
            try (InputStream input = context.getAssets().open("crm-fixtures/" + language + "-" + view + ".html")) {
                return PortalPage.parse(readBody(input), view);
            }
        }
        String header = PortalPolicy.authCookies(cookies);
        if (header.isEmpty()) throw new PortalPage.AuthRequired();
        HttpURLConnection connection = (HttpURLConnection) uri.toURL().openConnection();
        active = connection;
        try {
            connection.setRequestMethod("GET");
            connection.setInstanceFollowRedirects(false);
            connection.setConnectTimeout(12000);
            connection.setReadTimeout(20000);
            connection.setUseCaches(false);
            connection.setRequestProperty("Cookie", header);
            connection.setRequestProperty("Cache-Control", "no-store");
            connection.setRequestProperty("User-Agent", "HarmatCRM/1.0 Android ReadOnly");
            int status = connection.getResponseCode();
            if (status == 401 || status == 403 || (status >= 300 && status < 400)) throw new PortalPage.AuthRequired();
            if (status != 200) throw new IOException("Server unavailable");
            String contentType = connection.getContentType();
            if (contentType == null || !contentType.toLowerCase(java.util.Locale.ROOT).contains("text/html")) throw new PortalPage.SchemaChanged();
            try (InputStream input = connection.getInputStream()) {
                return PortalPage.parse(readBody(input), view);
            }
        } finally { connection.disconnect(); active = null; }
    }

    public void cancel() {
        HttpURLConnection connection = active;
        if (connection != null) connection.disconnect();
    }

    private static String readBody(InputStream input) throws IOException {
        ByteArrayOutputStream output = new ByteArrayOutputStream();
        byte[] buffer = new byte[8192];
        for (int count; (count = input.read(buffer)) != -1;) {
            if (output.size() + count > MAX_BYTES) throw new IOException("Response exceeds safe limit");
            output.write(buffer, 0, count);
        }
        return new String(output.toByteArray(), StandardCharsets.UTF_8);
    }
}
