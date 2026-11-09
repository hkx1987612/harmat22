package hu.harmat22.crm.mobile;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.IntentFilter;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.hardware.biometrics.BiometricPrompt;
import android.hardware.fingerprint.FingerprintManager;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.CancellationSignal;
import android.provider.Settings;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.Gravity;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowInsets;
import android.view.WindowManager;
import android.view.inputmethod.EditorInfo;
import android.view.inputmethod.InputMethodManager;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebStorage;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;

import java.io.ByteArrayInputStream;
import java.util.ArrayList;
import java.util.Date;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import javax.crypto.Cipher;

public class MainActivity extends Activity {
    private static final int INK = Color.rgb(36, 54, 49), GREEN = Color.rgb(49, 92, 77);
    private static final int MUTED = Color.rgb(91, 104, 98), BG = Color.rgb(239, 239, 234);
    private static final int SURFACE = Color.rgb(250, 250, 246), LINE = Color.rgb(209, 217, 208);
    private static final int SAGE = Color.rgb(224, 232, 223);
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private LinearLayout root, content, results;
    private PortalClient client;
    private SessionVault vault;
    private PortalPage page;
    private WebView loginView;
    private String cookie, view = "dashboard", query = "", status = "";
    private boolean chinese = true, fixture, foreground, loginPending, biometricPending;
    private int generation, visibleLimit = 40;
    private CancellationSignal cancellation;
    private final List<AlertDialog> dialogs = new ArrayList<>();
    private final CallbackOwner<CancellationSignal> biometricOwner = new CallbackOwner<>();
    private final CallbackOwner<WebView> loginOwner = new CallbackOwner<>();
    private Future<?> pendingRead;
    private final BroadcastReceiver screenLock = new BroadcastReceiver() {
        @Override public void onReceive(Context context, Intent intent) { lock(); }
    };

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        fixture = BuildConfig.DEBUG && getIntent().getBooleanExtra("demo", false);
        if (!fixture) getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
        chinese = getPreferences(MODE_PRIVATE).getBoolean("chinese", true);
        client = new PortalClient(this, fixture);
        vault = new SessionVault(this, fixture);
        CookieManager.getInstance().removeAllCookies(null);
        if (Build.VERSION.SDK_INT >= 33) registerReceiver(screenLock, new IntentFilter(Intent.ACTION_SCREEN_OFF), Context.RECEIVER_NOT_EXPORTED);
        else registerReceiver(screenLock, new IntentFilter(Intent.ACTION_SCREEN_OFF));
        gate("");
    }

    @Override protected void onStart() { super.onStart(); foreground = true; }
    @Override protected void onResume() { super.onResume(); foreground = true; }
    @Override protected void onPause() { foreground = false; lock(); super.onPause(); }
    @Override protected void onStop() { foreground = false; lock(); super.onStop(); }
    @Override protected void onDestroy() {
        client.cancel(); executor.shutdownNow();
        unregisterReceiver(screenLock);
        super.onDestroy();
    }
    @Override protected void onSaveInstanceState(Bundle state) {
        // Do not serialize customer data, cookies, WebView state, or decrypted content.
        super.onSaveInstanceState(state);
    }

    private String t(String zh, String hu) { return chinese ? zh : hu; }
    private int dp(float n) { return Math.round(n * getResources().getDisplayMetrics().density); }
    private LinearLayout column() { LinearLayout v = new LinearLayout(this); v.setOrientation(LinearLayout.VERTICAL); return v; }
    private LinearLayout row() { LinearLayout v = new LinearLayout(this); v.setGravity(Gravity.CENTER_VERTICAL); return v; }
    private TextView text(String value, int size, int color, boolean bold) {
        TextView v = new TextView(this); v.setText(value); v.setTextSize(size); v.setTextColor(color);
        v.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        v.setLineSpacing(dp(3), 1); v.setIncludeFontPadding(false); return v;
    }
    private GradientDrawable shape(int fill, int stroke) {
        GradientDrawable d = new GradientDrawable(); d.setColor(fill); d.setCornerRadius(dp(6));
        if (stroke != 0) d.setStroke(dp(1), stroke); return d;
    }
    private void space(LinearLayout parent, int value) { parent.addView(new View(this), new LinearLayout.LayoutParams(1, dp(value))); }
    private void line(LinearLayout parent) { View v = new View(this); v.setBackgroundColor(LINE); parent.addView(v, new LinearLayout.LayoutParams(-1, dp(1))); }
    private TextView command(String title, Runnable action, boolean primary) {
        TextView v = text(title, 16, primary ? Color.WHITE : GREEN, true);
        v.setGravity(Gravity.CENTER); v.setMinHeight(dp(52)); v.setPadding(dp(14), dp(12), dp(14), dp(12));
        v.setBackground(shape(primary ? GREEN : SURFACE, primary ? 0 : LINE));
        v.setFocusable(true); v.setOnClickListener(ignored -> action.run()); return v;
    }
    private void icon(LinearLayout parent, int resource, String label, Runnable action) {
        ImageButton b = new ImageButton(this); b.setImageResource(resource); b.setColorFilter(GREEN);
        b.setPadding(dp(12), dp(12), dp(12), dp(12));
        b.setContentDescription(label); b.setTooltipText(label); b.setBackground(shape(SURFACE, 0));
        b.setOnClickListener(v -> action.run()); parent.addView(b, new LinearLayout.LayoutParams(dp(48), dp(48)));
    }

    private void shell(boolean navigation) { shell(navigation, true); }
    private void shell(boolean navigation, boolean scrollable) {
        root = column(); root.setBackgroundColor(BG);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR | View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR);
        if (Build.VERSION.SDK_INT >= 30) root.setOnApplyWindowInsetsListener((v, insets) -> {
            android.graphics.Insets bars = insets.getInsets(WindowInsets.Type.systemBars());
            android.graphics.Insets ime = insets.getInsets(WindowInsets.Type.ime());
            v.setPadding(bars.left, bars.top, bars.right, Math.max(bars.bottom, ime.bottom)); return insets;
        }); else root.setFitsSystemWindows(true);
        setContentView(root);
        LinearLayout top = row(); top.setBackgroundColor(SURFACE); top.setPadding(dp(12), dp(5), dp(6), dp(5));
        ImageView logo = new ImageView(this); logo.setImageResource(getApplicationInfo().icon);
        logo.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO);
        top.addView(logo, new LinearLayout.LayoutParams(dp(36), dp(36)));
        TextView brand = text("Harmat CRM", 19, INK, true); brand.setPadding(dp(9), 0, 0, 0);
        top.addView(brand, new LinearLayout.LayoutParams(0, -2, 1));
        if (navigation) {
            icon(top, android.R.drawable.ic_popup_sync, t("刷新", "Frissítés"), this::load);
            icon(top, android.R.drawable.ic_lock_lock, t("锁定", "Zárolás"), this::lock);
        }
        icon(top, android.R.drawable.ic_menu_preferences, t("设置", "Beállítások"), this::settings);
        root.addView(top); line(root);
        if (fixture) {
            TextView badge = text(t("测试数据 · 不连接正式CRM", "Mintaadatok · Nincs éles kapcsolat"), 13, GREEN, true);
            badge.setPadding(dp(16), dp(8), dp(16), dp(8)); badge.setBackgroundColor(SAGE); root.addView(badge);
        }
        content = column(); content.setPadding(dp(18), dp(22), dp(18), dp(24));
        if (scrollable) {
            ScrollView scroll = new ScrollView(this); scroll.setFillViewport(true); scroll.addView(content);
            root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));
        } else root.addView(content, new LinearLayout.LayoutParams(-1, 0, 1));
        if (navigation) {
            line(root); LinearLayout nav = row(); nav.setBackgroundColor(SURFACE); nav.setBaselineAligned(false);
            nav.setMinimumHeight(dp(66));
            String[] views = {"dashboard", "inquiries", "deals", "properties"};
            String[] labels = {t("概览", "Áttekintés"), t("客户", "Ügyfelek"), t("跟单", "Ügyek"), t("房源", "Lakások")};
            int[] icons = {android.R.drawable.ic_menu_today, android.R.drawable.ic_menu_myplaces, android.R.drawable.ic_menu_recent_history, android.R.drawable.ic_menu_mapmode};
            for (int i = 0; i < views.length; i++) {
                final String target = views[i]; boolean selected = view.equals(target) || (target.equals("inquiries") && view.equals("clients"));
                LinearLayout tab = column(); tab.setGravity(Gravity.CENTER); tab.setPadding(dp(2), dp(9), dp(2), dp(9));
                tab.setMinimumHeight(dp(66));
                tab.setBackgroundColor(selected ? SAGE : SURFACE); tab.setContentDescription(labels[i]); tab.setFocusable(true);
                ImageView image = new ImageView(this); image.setImageResource(icons[i]); image.setColorFilter(selected ? GREEN : MUTED);
                image.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO); tab.addView(image, new LinearLayout.LayoutParams(dp(23), dp(23)));
                TextView title = text(labels[i], 12, selected ? GREEN : MUTED, selected); title.setGravity(Gravity.CENTER);
                title.setMaxLines(2); tab.addView(title);
                tab.setOnClickListener(v -> navigate(target)); nav.addView(tab, new LinearLayout.LayoutParams(0, -1, 1));
            }
            root.addView(nav);
        }
    }

    private void gate(String message) {
        shell(false); space(content, 30);
        content.addView(text(t("公司销售后台", "Értékesítési központ"), 26, INK, true));
        space(content, 12);
        content.addView(text(t("Harmat Lakópark · 安全登录", "Harmat Lakópark · Biztonságos belépés"), 15, MUTED, false));
        space(content, 30);
        if (!message.isEmpty()) { content.addView(text(message, 16, MUTED, false)); space(content, 20); }
        if (vault.exists()) {
            content.addView(command(t("指纹解锁", "Ujjlenyomatos feloldás"), () -> biometric(false), true)); space(content, 12);
        }
        content.addView(command(t("账号登录", "Belépés fiókkal"), this::login, !vault.exists()));
        if (fixture) { space(content, 16); content.addView(command(t("查看测试数据", "Mintaadatok megnyitása"), () -> {
            cookie = "wordpress_logged_in_demo=synthetic-session"; load();
        }, false)); }
    }

    private void navigate(String target) {
        view = target; query = ""; status = ""; visibleLimit = 40; load();
    }
    private String title() {
        return switch (view) {
            case "inquiries" -> t("询价汇总", "Érdeklődések");
            case "clients" -> t("客户档案", "Ügyféladatok");
            case "deals" -> t("销售跟单", "Értékesítési ügyek");
            case "properties" -> t("房源库存", "Lakáskészlet");
            default -> t("销售概览", "Értékesítési áttekintés");
        };
    }

    private void load() { fetch(false); }
    private void fetch(boolean offerBiometric) {
        if (cookie == null || !foreground) { gate(""); return; }
        final int request = ++generation;
        final String requestedView = view, language = chinese ? "zh" : "hu", session = cookie;
        page = null; shell(true); content.addView(text(title(), 25, INK, true)); space(content, 24);
        content.addView(new ProgressBar(this));
        if (pendingRead != null) { pendingRead.cancel(true); client.cancel(); }
        pendingRead = executor.submit(() -> {
            try {
                PortalPage loaded = client.read(requestedView, language, session);
                runOnUiThread(() -> {
                    if (!foreground || request != generation || cookie == null) return;
                    page = loaded; render();
                    if (offerBiometric && !vault.exists()) offerBiometric();
                });
            } catch (PortalPage.AuthRequired unauthorized) {
                runOnUiThread(() -> {
                    if (!foreground || request != generation) return;
                    forget(); gate(t("登录已失效，或当前账号没有销售后台权限。请重新登录。", "A belépés lejárt, vagy a fióknak nincs hozzáférése. Jelentkezzen be újra."));
                });
            } catch (Exception failed) {
                runOnUiThread(() -> {
                    if (!foreground || request != generation || cookie == null) return;
                    shell(true); content.addView(text(title(), 25, INK, true)); space(content, 18);
                    content.addView(text(failed instanceof PortalPage.SchemaChanged
                        ? t("页面结构有变化，暂时无法读取。未显示旧数据。", "Az oldal szerkezete megváltozott. Régi adatokat nem jelenítünk meg.")
                        : t("暂时无法连接CRM，请检查网络后重试。", "A CRM nem érhető el. Ellenőrizze a hálózatot, majd próbálja újra."), 16, MUTED, false));
                    space(content, 22); content.addView(command(t("重试", "Újra"), this::load, true));
                });
            }
        });
    }

    private void render() {
        shell(true); content.addView(text(title(), 25, INK, true)); space(content, 8);
        content.addView(text(page.user + " · " + page.role, 14, MUTED, false)); space(content, 5);
        content.addView(text(t("更新于 ", "Frissítve: ") + new java.text.SimpleDateFormat("HH:mm", java.util.Locale.ROOT).format(new Date(page.fetchedAt)), 13, MUTED, false));
        space(content, 22);
        if (view.equals("dashboard")) { overview(); return; }
        if (view.equals("inquiries") || view.equals("clients")) {
            LinearLayout tabs = row();
            for (String target : new String[]{"inquiries", "clients"}) {
                TextView tab = command(target.equals("inquiries") ? t("询价汇总", "Érdeklődések") : t("客户档案", "Ügyfelek"), () -> navigate(target), view.equals(target));
                tabs.addView(tab, new LinearLayout.LayoutParams(0, -2, 1));
            }
            content.addView(tabs); space(content, 18);
        }
        EditText search = new EditText(this); search.setSingleLine(true); search.setTextSize(16); search.setTextColor(INK);
        search.setImeOptions(EditorInfo.IME_ACTION_SEARCH);
        search.setOnEditorActionListener((field, action, event) -> {
            if (action != EditorInfo.IME_ACTION_SEARCH && (event == null || event.getKeyCode() != KeyEvent.KEYCODE_ENTER)) return false;
            InputMethodManager keyboard = getSystemService(InputMethodManager.class);
            if (keyboard != null) keyboard.hideSoftInputFromWindow(search.getWindowToken(), 0);
            content.setFocusableInTouchMode(true); content.requestFocus();
            return true;
        });
        search.setHintTextColor(MUTED); search.setHint(t("姓名、电话、邮箱或房号", "Név, telefon, e-mail vagy lakás"));
        search.setContentDescription(t("搜索记录", "Rekordok keresése")); search.setBackground(shape(SURFACE, LINE));
        search.setPadding(dp(12), dp(10), dp(12), dp(10)); search.setText(query); content.addView(search, new LinearLayout.LayoutParams(-1, dp(52)));
        space(content, 12);
        LinkedHashSet<String> options = new LinkedHashSet<>(); options.add(t("全部状态", "Minden állapot"));
        for (PortalPage.Record record : page.records) if (!record.status.isEmpty()) options.add(record.status);
        List<String> labels = new ArrayList<>(options);
        Spinner spinner = new Spinner(this); spinner.setContentDescription(t("状态筛选", "Állapotszűrő"));
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, labels);
        spinner.setAdapter(adapter); spinner.setSelection(status.isEmpty() ? 0 : Math.max(0, labels.indexOf(status)));
        spinner.setOnItemSelectedListener(new android.widget.AdapterView.OnItemSelectedListener() {
            public void onNothingSelected(android.widget.AdapterView<?> parent) {}
            public void onItemSelected(android.widget.AdapterView<?> parent, View selected, int position, long id) {
                status = position == 0 ? "" : labels.get(position); visibleLimit = 40; updateRecords();
            }
        });
        content.addView(spinner, new LinearLayout.LayoutParams(-1, dp(48))); space(content, 14);
        results = column(); content.addView(results); updateRecords();
        search.addTextChangedListener(new TextWatcher() {
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            public void onTextChanged(CharSequence s, int start, int before, int count) { query = s.toString(); visibleLimit = 40; updateRecords(); }
            public void afterTextChanged(Editable editable) {}
        });
    }

    private void overview() {
        for (int i = 0; i < page.metrics.size(); i += 2) {
            LinearLayout metrics = row();
            for (int j = i; j < Math.min(i + 2, page.metrics.size()); j++) {
                PortalPage.Field metric = page.metrics.get(j); LinearLayout cell = column(); cell.setPadding(0, dp(8), dp(10), dp(15));
                TextView label = text(metric.label, 14, MUTED, false); label.setMinLines(2); cell.addView(label);
                cell.addView(text(metric.value, 30, GREEN, true)); metrics.addView(cell, new LinearLayout.LayoutParams(0, -2, 1));
            }
            content.addView(metrics);
        }
        space(content, 12); content.addView(text(t("每日访问", "Napi forgalom"), 20, INK, true)); space(content, 14);
        if (page.days.isEmpty()) content.addView(text(t("暂无可查看的访问数据", "Nincs megjeleníthető forgalmi adat"), 15, MUTED, false));
        int website = 0, walkin = 0, pageviews = 0; boolean totalsKnown = !page.days.isEmpty();
        for (List<String> day : page.days) {
            LinearLayout entry = column(); entry.setPadding(0, dp(13), 0, dp(13));
            entry.addView(text(day.get(0), 15, INK, true)); space(entry, 7);
            entry.addView(text(t("访客 ", "Látogató ") + day.get(1) + "   ·   " + t("浏览 ", "Megtekintés ") + day.get(2), 15, INK, false));
            space(entry, 5); entry.addView(text(t("网站 ", "Web ") + day.get(3) + "   ·   " + t("自来 ", "Saját ") + day.get(4) + "   ·   " + t("总计 ", "Összes ") + day.get(5), 14, GREEN, false));
            content.addView(entry); line(content);
            try { website += Integer.parseInt(day.get(3)); walkin += Integer.parseInt(day.get(4)); pageviews += Integer.parseInt(day.get(2)); }
            catch (NumberFormatException missing) { totalsKnown = false; }
        }
        if (totalsKnown) {
            space(content, 14); content.addView(text(t("7天合计：浏览 ", "7 nap összesen: megtekintés ") + pageviews
                + " · " + t("网站 ", "web ") + website + " · " + t("自来 ", "saját ") + walkin
                + " · " + t("询价 ", "érdeklődés ") + (website + walkin), 14, GREEN, true));
        }
        space(content, 12); content.addView(text(t("仅含同意统计Cookie的访问。询价总计不含经纪人；访客数不跨日相加。", "Csak statisztikai hozzájárulással mért forgalom. A közvetítői érdeklődés nincs az összegben; a napi látogatók nem összegezhetők."), 12, MUTED, false));
        space(content, 26); content.addView(text(t("最近询价", "Legújabb érdeklődések"), 20, INK, true)); space(content, 14);
        for (PortalPage.Record record : page.records) addRecord(content, record);
        space(content, 8); content.addView(command(t("全部询价", "Összes érdeklődés"), () -> navigate("inquiries"), false));
    }

    private void updateRecords() {
        if (results == null || page == null) return;
        results.removeAllViews(); int count = 0;
        for (PortalPage.Record record : page.records) {
            if (!record.matches(query) || (!status.isEmpty() && !status.equals(record.status))) continue;
            count++; if (count <= visibleLimit) addRecord(results, record);
        }
        if (count == 0) results.addView(text(t("没有匹配的记录", "Nincs találat"), 18, MUTED, true));
        else {
            space(results, 6); results.addView(text(count + t("条记录", " rekord"), 13, MUTED, false));
            if (count > visibleLimit) { space(results, 12); results.addView(command(t("加载更多", "Továbbiak"), () -> { visibleLimit += 40; updateRecords(); }, false)); }
        }
    }
    private void addRecord(LinearLayout parent, PortalPage.Record record) {
        LinearLayout item = column(); item.setPadding(dp(15), dp(16), dp(15), dp(16)); item.setBackground(shape(SURFACE, LINE));
        item.addView(text(record.title, 18, INK, true)); space(item, 8);
        if (!record.status.isEmpty()) { item.addView(text(record.status, 14, GREEN, true)); space(item, 7); }
        TextView subtitle = text(record.subtitle, 15, MUTED, false); subtitle.setMaxLines(3); item.addView(subtitle);
        item.setContentDescription(record.title + t(" 详情", " adatlap")); item.setFocusable(true);
        item.setOnClickListener(v -> details(record)); LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, -2); lp.bottomMargin = dp(12); parent.addView(item, lp);
    }
    private void details(PortalPage.Record record) {
        if (!foreground || cookie == null || page == null) return;
        LinearLayout body = column(); body.setPadding(dp(22), dp(14), dp(22), dp(22));
        for (PortalPage.Field field : record.fields) {
            body.addView(text(field.label, 13, MUTED, true)); space(body, 6);
            body.addView(text(field.value, 16, INK, false)); space(body, 20);
        }
        ScrollView scroll = new ScrollView(this); scroll.addView(body);
        showDialog(new AlertDialog.Builder(this).setTitle(record.title).setView(scroll)
            .setPositiveButton(t("关闭", "Bezárás"), null));
    }

    private void showDialog(AlertDialog.Builder builder) {
        if (!foreground || isFinishing()) return;
        AlertDialog dialog = builder.create();
        if (!fixture) dialog.getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
        dialogs.add(dialog);
        dialog.setOnDismissListener(ignored -> dialogs.remove(dialog));
        dialog.show();
    }

    private DialogInterface.OnClickListener guarded(DialogInterface.OnClickListener action) {
        final int request = generation;
        return (dialog, which) -> { if (foreground && request == generation) action.onClick(dialog, which); };
    }

    private void dismissDialogs() {
        List<AlertDialog> previous = new ArrayList<>(dialogs); dialogs.clear();
        for (AlertDialog dialog : previous) { dialog.setOnDismissListener(null); dialog.dismiss(); }
    }

    private void settings() {
        String[] actions = cookie == null
            ? new String[]{"中文 / Magyar", t("手机指纹设置", "Ujjlenyomat beállítása"), t("清除此设备登录", "Belépés törlése erről az eszközről")}
            : new String[]{"中文 / Magyar", t("启用指纹解锁", "Ujjlenyomatos feloldás"), t("退出此设备", "Kilépés ezen az eszközön")};
        showDialog(new AlertDialog.Builder(this).setTitle(t("设置", "Beállítások")).setItems(actions, guarded((dialog, which) -> {
            if (which == 0) {
                chinese = !chinese; getPreferences(MODE_PRIVATE).edit().putBoolean("chinese", chinese).apply();
                if (cookie == null) { closeLogin(); gate(""); } else load();
            } else if (which == 1) {
                if (cookie == null) startActivity(new Intent(Settings.ACTION_SECURITY_SETTINGS)); else biometric(true);
            } else {
                showDialog(new AlertDialog.Builder(this).setTitle(t("清除此设备登录？", "Törli az eszköz belépését?"))
                    .setMessage(t("此设备保存的登录会话将被移除，其他设备不受影响。", "A helyi belépési adatok törlődnek. Más eszközök belépése változatlan."))
                    .setNegativeButton(t("取消", "Mégse"), null).setPositiveButton(t("清除", "Törlés"), guarded((d, w) -> { forget(); gate(""); })));
            }
        })));
    }
    private boolean fingerprintAvailable() {
        if (Build.VERSION.SDK_INT >= 30) {
            android.hardware.biometrics.BiometricManager manager = getSystemService(android.hardware.biometrics.BiometricManager.class);
            return manager != null && manager.canAuthenticate(android.hardware.biometrics.BiometricManager.Authenticators.BIOMETRIC_STRONG)
                == android.hardware.biometrics.BiometricManager.BIOMETRIC_SUCCESS;
        }
        FingerprintManager manager = getSystemService(FingerprintManager.class);
        return manager != null && manager.isHardwareDetected() && manager.hasEnrolledFingerprints();
    }
    private void offerBiometric() {
        if (!fingerprintAvailable()) return;
        showDialog(new AlertDialog.Builder(this).setTitle(t("启用指纹解锁？", "Bekapcsolja az ujjlenyomatos feloldást?"))
            .setMessage(t("登录会话仅加密保存在这台手机。服务器登录过期后仍需重新登录。", "A belépés titkosítva, csak ezen a telefonon marad. Lejárt szerveres munkamenetnél új belépés szükséges."))
            .setNegativeButton(t("暂不", "Most nem"), null).setPositiveButton(t("启用", "Bekapcsolás"), guarded((d, w) -> biometric(true))));
    }
    private void biometric(boolean save) {
        if (!foreground || biometricPending || (save && cookie == null)) return;
        if (save && vault.exists()) {
            showDialog(new AlertDialog.Builder(this).setMessage(t("此设备已启用指纹解锁。", "Az ujjlenyomatos feloldás már be van kapcsolva."))
                .setPositiveButton(t("知道了", "Rendben"), null)); return;
        }
        if (!fingerprintAvailable()) {
            showDialog(new AlertDialog.Builder(this).setMessage(t("请先在手机安全设置中录入指纹，也可以使用CRM账号登录。", "Előbb állítson be ujjlenyomatot a telefonon, vagy lépjen be CRM-fiókjával."))
                .setPositiveButton(t("知道了", "Rendben"), null)); return;
        }
        final int request = generation;
        final String sessionToSave = cookie;
        final CancellationSignal signal = new CancellationSignal();
        final long operation = biometricOwner.begin(signal);
        cancellation = signal; biometricPending = true;
        try {
            Cipher cipher = save ? vault.encryptionCipher() : vault.decryptionCipher();
            BiometricPrompt.Builder builder = new BiometricPrompt.Builder(this)
                .setTitle(t("Harmat CRM 指纹验证", "Harmat CRM ujjlenyomat"))
                .setSubtitle(save ? t("加密保存此设备登录", "Belépés titkosított mentése") : t("解锁销售后台", "Értékesítési központ feloldása"))
                .setNegativeButton(t("取消", "Mégse"), getMainExecutor(), (d, w) -> finishBiometric(operation, signal, request));
            if (Build.VERSION.SDK_INT >= 30) builder.setAllowedAuthenticators(android.hardware.biometrics.BiometricManager.Authenticators.BIOMETRIC_STRONG);
            builder.build().authenticate(new BiometricPrompt.CryptoObject(cipher), signal, getMainExecutor(), new BiometricPrompt.AuthenticationCallback() {
                @Override public void onAuthenticationError(int error, CharSequence description) { finishBiometric(operation, signal, request); }
                @Override public void onAuthenticationSucceeded(BiometricPrompt.AuthenticationResult result) {
                    if (!finishBiometric(operation, signal, request)) return;
                    try {
                        Cipher authenticated = result.getCryptoObject().getCipher();
                        if (save) {
                            vault.save(authenticated, sessionToSave);
                            android.widget.Toast.makeText(MainActivity.this, t("指纹解锁已启用", "Ujjlenyomatos feloldás bekapcsolva"), android.widget.Toast.LENGTH_SHORT).show();
                        }
                        else { cookie = vault.unlock(authenticated); load(); }
                    } catch (Exception invalidKey) { forget(); gate(t("安全验证已变化，请重新登录。", "A biztonsági adatok megváltoztak. Lépjen be újra.")); }
                }
            });
        } catch (Exception invalidKey) {
            if (finishBiometric(operation, signal, request)) {
                forget(); gate(t("指纹登录不可用，请用账号重新登录。", "Az ujjlenyomatos belépés nem használható. Lépjen be fiókjával."));
            }
        }
    }

    private boolean finishBiometric(long operation, CancellationSignal signal, int request) {
        if (!foreground || request != generation || !biometricOwner.finish(operation, signal)) return false;
        biometricPending = false; cancellation = null;
        return true;
    }

    private void login() {
        if (vault.exists()) {
            showDialog(new AlertDialog.Builder(this).setTitle(t("使用账号重新登录？", "Új belépés fiókkal?"))
                .setMessage(t("将清除此设备的旧指纹会话，登录后可重新启用。", "A korábbi helyi ujjlenyomatos belépés törlődik. Belépés után újra bekapcsolható."))
                .setNegativeButton(t("取消", "Mégse"), null)
                .setPositiveButton(t("继续", "Folytatás"), guarded((d, w) -> { vault.clear(); startLogin(); })));
        } else startLogin();
    }
    private void startLogin() {
        if (fixture) { cookie = "wordpress_logged_in_demo=synthetic-session"; fetch(true); return; }
        lock(); shell(false, false); loginPending = false;
        content.addView(text(t("登录Harmat销售后台", "Belépés a Harmat értékesítésbe"), 23, INK, true)); space(content, 12);
        content.addView(text("https://harmat22.hu/sales/", 13, MUTED, false)); space(content, 12);
        loginView = new WebView(this); WebView.setWebContentsDebuggingEnabled(false);
        final WebView target = loginView;
        final long operation = loginOwner.begin(target);
        WebSettings settings = loginView.getSettings(); settings.setJavaScriptEnabled(false);
        settings.setDomStorageEnabled(false); settings.setAllowFileAccess(false); settings.setAllowContentAccess(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW); settings.setSafeBrowsingEnabled(true);
        settings.setSaveFormData(false); settings.setCacheMode(WebSettings.LOAD_NO_CACHE);
        CookieManager cookies = CookieManager.getInstance(); cookies.setAcceptCookie(true); cookies.setAcceptThirdPartyCookies(loginView, false);
        loginView.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest request) {
                return !ownsLogin(v, operation) || !PortalPolicy.loginNavigation(request.getUrl().toString());
            }
            @Override public WebResourceResponse shouldInterceptRequest(WebView v, WebResourceRequest request) {
                if (!PortalPolicy.sameOrigin(request.getUrl().toString())) return new WebResourceResponse("text/plain", "UTF-8", 403, "Blocked", java.util.Collections.emptyMap(), new ByteArrayInputStream(new byte[0]));
                return null;
            }
            @Override public void onPageStarted(WebView v, String url, android.graphics.Bitmap favicon) { captureLogin(v, operation); }
            @Override public void onPageFinished(WebView v, String url) { captureLogin(v, operation); }
            @Override public void onReceivedSslError(WebView v, SslErrorHandler handler, SslError error) {
                handler.cancel();
                if (ownsLogin(v, operation)) { closeLogin(); gate(t("安全连接失败，已停止连接。", "A biztonságos kapcsolat sikertelen. A kapcsolat leállt.")); }
            }
            @Override public void onReceivedError(WebView v, WebResourceRequest r, android.webkit.WebResourceError e) {
                if (r.isForMainFrame() && ownsLogin(v, operation)) { closeLogin(); gate(t("登录页暂时无法打开，请稍后重试。", "A belépési oldal nem érhető el. Próbálja újra később.")); }
            }
        });
        // The WebView owns scrolling; a parent ScrollView steals its login-form gestures.
        content.addView(loginView, new LinearLayout.LayoutParams(-1, 0, 1));
        content.addView(command(t("取消登录", "Belépés megszakítása"), () -> { closeLogin(); gate(""); }, false));
        cookies.removeAllCookies(ignored -> { if (ownsLogin(target, operation)) target.loadUrl(PortalPolicy.ORIGIN + "/sales/?wp_lang=" + (chinese ? "zh_CN" : "hu_HU")); });
    }
    private boolean ownsLogin(WebView target, long operation) {
        return foreground && loginView == target && loginOwner.matches(operation, target);
    }
    private void captureLogin(WebView target, long operation) {
        if (!ownsLogin(target, operation) || loginPending) return;
        String captured = PortalPolicy.authCookies(CookieManager.getInstance().getCookie(PortalPolicy.ORIGIN + "/sales/"));
        if (captured.isEmpty()) return;
        loginPending = true; closeLogin(); cookie = captured; view = "dashboard"; fetch(true);
    }
    private void closeLogin() {
        loginOwner.invalidate();
        WebView previous = loginView; loginView = null; loginPending = false;
        if (previous != null) {
            previous.stopLoading(); previous.clearHistory(); previous.clearCache(true);
            if (previous.getParent() instanceof android.view.ViewGroup) ((android.view.ViewGroup) previous.getParent()).removeView(previous);
            previous.destroy();
        }
        CookieManager.getInstance().removeAllCookies(null); WebStorage.getInstance().deleteAllData();
    }
    private void lock() {
        generation++; if (pendingRead != null) pendingRead.cancel(true); client.cancel();
        biometricOwner.invalidate();
        CancellationSignal previous = cancellation; cancellation = null;
        if (previous != null) previous.cancel();
        biometricPending = false; cookie = null; page = null; results = null;
        query = ""; status = ""; closeLogin();
        dismissDialogs();
        gate("");
    }
    private void forget() { lock(); vault.clear(); }
    @Override public void onBackPressed() {
        if (loginView != null) { closeLogin(); gate(""); }
        else if (cookie != null && !view.equals("dashboard")) navigate("dashboard");
        else { lock(); super.onBackPressed(); }
    }
}
