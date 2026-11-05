package hu.harmat22.crm.preview;

import android.app.Activity;
import android.app.AlertDialog;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.Gravity;
import android.view.View;
import android.view.WindowInsets;
import android.view.inputmethod.InputMethodManager;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import java.util.List;
import java.util.Locale;

public class MainActivity extends Activity {
    private static final int INK = Color.rgb(39, 54, 50);
    private static final int MUTED = Color.rgb(91, 102, 99);
    private static final int GREEN = Color.rgb(54, 95, 83);
    private static final int BACKGROUND = Color.rgb(240, 239, 235);
    private static final int SURFACE = Color.rgb(251, 250, 247);
    private static final int LINE = Color.rgb(211, 217, 211);
    private static final int SAGE = Color.rgb(226, 233, 225);
    private static final int AMBER = Color.rgb(128, 88, 27);
    private LinearLayout root, body, results;
    private LinearLayout topBar, bottomBar, pageHeading;
    private ScrollView scroll;
    private boolean chinese;
    private int section;
    private String query = "";
    private DemoData.Source source;
    private DemoData.Status status;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        chinese = getPreferences(MODE_PRIVATE).getBoolean("chinese", true);
        if (state != null) {
            section = state.getInt("section");
            query = state.getString("query", "");
            source = readSource(state.getInt("source", -1));
            status = readStatus(state.getInt("status", -1));
        }
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR
            | View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR);
        render();
    }

    @Override protected void onSaveInstanceState(Bundle state) {
        state.putInt("section", section);
        state.putString("query", query);
        state.putInt("source", source == null ? -1 : source.ordinal());
        state.putInt("status", status == null ? -1 : status.ordinal());
        super.onSaveInstanceState(state);
    }

    private DemoData.Source readSource(int value) {
        return value >= 0 && value < DemoData.Source.values().length ? DemoData.Source.values()[value] : null;
    }
    private DemoData.Status readStatus(int value) {
        return value >= 0 && value < DemoData.Status.values().length ? DemoData.Status.values()[value] : null;
    }
    private String t(String zh, String hu) { return chinese ? zh : hu; }
    private int dp(float value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    private LinearLayout col() { LinearLayout v = new LinearLayout(this); v.setOrientation(LinearLayout.VERTICAL); return v; }
    private LinearLayout row() { LinearLayout v = new LinearLayout(this); v.setGravity(Gravity.CENTER_VERTICAL); return v; }
    private LinearLayout.LayoutParams wrap() { return new LinearLayout.LayoutParams(-1, -2); }
    private TextView text(String value, int size, int color, boolean bold) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextSize(size);
        view.setTextColor(color);
        view.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        view.setIncludeFontPadding(false);
        view.setLineSpacing(dp(3), 1);
        return view;
    }
    private GradientDrawable background(int fill, int stroke, int radius) {
        GradientDrawable shape = new GradientDrawable();
        shape.setColor(fill);
        shape.setCornerRadius(dp(radius));
        if (stroke != 0) shape.setStroke(dp(1), stroke);
        return shape;
    }
    private void space(LinearLayout parent, int height) {
        parent.addView(new View(this), new LinearLayout.LayoutParams(1, dp(height)));
    }
    private void line(LinearLayout parent) {
        View line = new View(this);
        line.setBackgroundColor(LINE);
        parent.addView(line, new LinearLayout.LayoutParams(-1, dp(1)));
    }
    private void heading(String title, String detail) {
        pageHeading = col();
        pageHeading.addView(text(title, 25, INK, true));
        space(pageHeading, 8);
        pageHeading.addView(text(detail, 14, MUTED, false));
        space(pageHeading, 24);
        body.addView(pageHeading);
    }
    private void subheading(String value) {
        space(body, 24);
        body.addView(text(value, 19, INK, true));
        space(body, 14);
    }
    private TextView command(String label, Runnable callback) {
        TextView v = text(label, 15, GREEN, true);
        v.setGravity(Gravity.CENTER);
        v.setMinHeight(dp(48));
        v.setPadding(dp(10), dp(10), dp(10), dp(10));
        v.setBackground(background(SURFACE, LINE, 6));
        v.setOnClickListener(ignored -> callback.run());
        v.setFocusable(true);
        return v;
    }

    private void render() {
        topBar = null;
        bottomBar = null;
        pageHeading = null;
        root = col();
        root.setBackgroundColor(BACKGROUND);
        if (android.os.Build.VERSION.SDK_INT >= 30) {
            root.setOnApplyWindowInsetsListener((view, insets) -> {
                android.graphics.Insets bars = insets.getInsets(WindowInsets.Type.systemBars());
                android.graphics.Insets keyboard = insets.getInsets(WindowInsets.Type.ime());
                int chromeVisibility = insets.isVisible(WindowInsets.Type.ime()) ? View.GONE : View.VISIBLE;
                if (topBar != null) topBar.setVisibility(chromeVisibility);
                if (bottomBar != null) bottomBar.setVisibility(chromeVisibility);
                if (pageHeading != null && section == 1) pageHeading.setVisibility(chromeVisibility);
                view.setPadding(bars.left, bars.top, bars.right, Math.max(bars.bottom, keyboard.bottom));
                return insets;
            });
        } else root.setFitsSystemWindows(true);
        setContentView(root);

        LinearLayout header = row();
        topBar = header;
        header.setPadding(dp(16), dp(8), dp(8), dp(8));
        header.setBackgroundColor(SURFACE);
        ImageView logo = new ImageView(this);
        logo.setImageResource(getApplicationInfo().icon);
        logo.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO);
        header.addView(logo, new LinearLayout.LayoutParams(dp(40), dp(40)));
        TextView title = text("Harmat CRM", 19, INK, true);
        title.setPadding(dp(10), 0, 0, 0);
        header.addView(title, new LinearLayout.LayoutParams(0, -2, 1));
        ImageButton settings = new ImageButton(this);
        settings.setImageResource(android.R.drawable.ic_menu_preferences);
        settings.setColorFilter(GREEN);
        settings.setBackground(background(SURFACE, 0, 6));
        settings.setContentDescription(t("设置", "Beállítások"));
        settings.setTooltipText(settings.getContentDescription());
        settings.setOnClickListener(v -> navigate(4));
        header.addView(settings, new LinearLayout.LayoutParams(dp(48), dp(48)));
        root.addView(header);
        TextView badge = text(t("离线测试版 · 演示数据", "Offline tesztverzió · Mintaadatok"), 13, GREEN, true);
        badge.setBackgroundColor(SAGE);
        badge.setPadding(dp(20), dp(9), dp(20), dp(9));
        root.addView(badge, wrap());
        scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        FrameLayout container = new FrameLayout(this);
        body = col();
        body.setPadding(dp(20), dp(24), dp(20), dp(28));
        int available = getResources().getDisplayMetrics().widthPixels;
        FrameLayout.LayoutParams width = new FrameLayout.LayoutParams(Math.min(available, dp(680)), -2, Gravity.TOP | Gravity.CENTER_HORIZONTAL);
        container.addView(body, width);
        scroll.addView(container);
        root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));
        if (section == 0) overview();
        if (section == 1) clients();
        if (section == 2) followUps();
        if (section == 3) properties();
        if (section == 4) settings();
        line(root);
        LinearLayout navigation = row();
        bottomBar = navigation;
        navigation.setBackgroundColor(SURFACE);
        String[] labels = {t("概览", "Áttekintés"), t("客户", "Ügyfelek"), t("跟进", "Követés"), t("房源", "Lakások")};
        int[] icons = {android.R.drawable.ic_menu_today, android.R.drawable.ic_menu_myplaces,
            android.R.drawable.ic_menu_recent_history, android.R.drawable.ic_menu_mapmode};
        for (int i = 0; i < labels.length; i++) {
            final int target = i;
            LinearLayout tab = col();
            tab.setGravity(Gravity.CENTER);
            tab.setPadding(dp(2), dp(10), dp(2), dp(10));
            tab.setBackgroundColor(section == i ? SAGE : SURFACE);
            ImageView icon = new ImageView(this);
            icon.setImageResource(icons[i]);
            icon.setColorFilter(section == i ? GREEN : MUTED);
            icon.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO);
            tab.addView(icon, new LinearLayout.LayoutParams(dp(22), dp(22)));
            space(tab, 5);
            TextView label = text(labels[i], 12, section == i ? GREEN : MUTED, section == i);
            label.setGravity(Gravity.CENTER);
            tab.addView(label);
            tab.setContentDescription(labels[i]);
            tab.setFocusable(true);
            tab.setOnClickListener(v -> navigate(target));
            navigation.addView(tab, new LinearLayout.LayoutParams(0, -1, 1));
        }
        root.addView(navigation, new LinearLayout.LayoutParams(-1, -2));
    }

    private void navigate(int target) {
        ((InputMethodManager) getSystemService(INPUT_METHOD_SERVICE)).hideSoftInputFromWindow(root.getWindowToken(), 0);
        section = target;
        render();
    }

    private void overview() {
        heading(t("销售概览", "Értékesítési áttekintés"), t("示例日期 2026.11.05", "Minta dátuma: 2026.11.05."));
        DemoData.Day day = DemoData.DAYS.get(0);
        LinearLayout first = row();
        metric(first, t("访客", "Látogatók"), day.visitors, GREEN);
        metric(first, t("浏览", "Megtekintések"), day.views, GREEN);
        body.addView(first);
        space(body, 18);
        LinearLayout second = row();
        metric(second, t("网站询价", "Webes érdeklődés"), day.website, GREEN);
        metric(second, t("自来询价", "Személyes érdeklődés"), day.walkIn, AMBER);
        body.addView(second);
        subheading(t("每日访问", "Napi forgalom"));
        dailyTable();
        subheading(t("最新跟进", "Legutóbbi kapcsolat"));
        for (DemoData.Client client : DemoData.select("", null, null).subList(0, 2)) addClient(body, client, false);
        body.addView(command(t("查看全部跟进", "Összes kapcsolat"), () -> navigate(2)));
    }
    private void metric(LinearLayout parent, String label, int value, int color) {
        LinearLayout cell = col();
        cell.setPadding(0, dp(3), dp(12), dp(3));
        TextView caption = text(label, 14, MUTED, false);
        caption.setMinLines(2);
        cell.addView(caption);
        space(cell, 6);
        cell.addView(text(String.valueOf(value), 30, color, true));
        parent.addView(cell, new LinearLayout.LayoutParams(0, -2, 1));
    }
    private void dailyTable() {
        tableRow(new String[]{t("日期", "Nap"), "UV", "PV", t("网站", "Web"), t("自来", "Szem."), t("总计", "Össz.")}, true);
        for (DemoData.Day day : DemoData.DAYS) tableRow(new String[]{day.date.toString().substring(5),
            "" + day.visitors, "" + day.views, "" + day.website, "" + day.walkIn, "" + day.enquiries()}, false);
        int[] total = DemoData.totals();
        tableRow(new String[]{t("7天", "7 nap"), "—", "" + total[0], "" + total[1], "" + total[2], "" + total[3]}, true);
        space(body, 10);
        body.addView(text(t("UV 访客 · PV 浏览；总计为网站与自来询价之和。跨日访客不直接相加。",
            "UV: látogatók · PV: megtekintések. Össz.: webes és személyes érdeklődések. A napi UV nem összegezhető."), 13, MUTED, false));
    }
    private void tableRow(String[] values, boolean emphasis) {
        LinearLayout row = row();
        row.setPadding(dp(3), dp(12), dp(3), dp(12));
        row.setBackgroundColor(emphasis ? SAGE : SURFACE);
        for (int i = 0; i < values.length; i++) {
            TextView cell = text(values[i], 13, INK, emphasis);
            cell.setGravity(i == 0 ? Gravity.START : Gravity.CENTER);
            row.addView(cell, new LinearLayout.LayoutParams(0, -2, i == 0 ? 1.15f : 1));
        }
        body.addView(row);
        line(body);
    }

    private void clients() {
        heading(t("客户", "Ügyfelek"), t("最近更新优先", "Legutóbb frissített elöl"));
        EditText search = new EditText(this);
        search.setTextSize(16);
        search.setTextColor(INK);
        search.setHintTextColor(MUTED);
        search.setSingleLine(true);
        search.setHint(t("搜索姓名、房号或编号", "Név, lakás vagy azonosító"));
        search.setContentDescription(t("搜索客户", "Ügyfél keresése"));
        search.setPadding(dp(14), dp(12), dp(14), dp(12));
        search.setBackground(background(SURFACE, LINE, 6));
        search.setText(query);
        body.addView(search, new LinearLayout.LayoutParams(-1, dp(52)));
        space(body, 14);
        LinearLayout filters = row();
        filter(filters, t("全部", "Mind"), source == null, () -> setSource(null));
        filter(filters, t("网站询价", "Webes"), source == DemoData.Source.WEBSITE, () -> setSource(DemoData.Source.WEBSITE));
        filter(filters, t("自来询价", "Személyes"), source == DemoData.Source.WALK_IN, () -> setSource(DemoData.Source.WALK_IN));
        body.addView(filters);
        space(body, 16);
        results = col();
        body.addView(results);
        updateClients();
        search.addTextChangedListener(new TextWatcher() {
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            public void onTextChanged(CharSequence s, int start, int before, int count) { query = s.toString(); updateClients(); }
            public void afterTextChanged(Editable editable) {}
        });
    }
    private void setSource(DemoData.Source next) { source = next; render(); }
    private void filter(LinearLayout parent, String label, boolean selected, Runnable callback) {
        TextView v = command(label, callback);
        v.setBackground(background(selected ? GREEN : SURFACE, selected ? GREEN : LINE, 0));
        v.setTextColor(selected ? Color.WHITE : GREEN);
        v.setSelected(selected);
        parent.addView(v, new LinearLayout.LayoutParams(0, -2, 1));
    }
    private void updateClients() {
        results.removeAllViews();
        List<DemoData.Client> clients = DemoData.select(query, source, null);
        for (DemoData.Client client : clients) addClient(results, client, true);
        if (clients.isEmpty()) {
            results.addView(text(t("没有匹配的客户", "Nincs találat"), 18, MUTED, true));
            space(results, 16);
            results.addView(command(t("清除筛选", "Szűrők törlése"), () -> { query = ""; source = null; render(); }));
        }
    }
    private String status(DemoData.Status value) {
        if (value == DemoData.Status.NEW) return t("新询价", "Új érdeklődő");
        if (value == DemoData.Status.CONTACTED) return t("已联系", "Kapcsolatfelvétel");
        return t("已到访", "Személyes látogatás");
    }
    private String source(DemoData.Source value) {
        return value == DemoData.Source.WEBSITE ? t("网站询价", "Webes érdeklődés") : t("自来询价", "Személyes érdeklődés");
    }
    private void addClient(LinearLayout parent, DemoData.Client client, boolean full) {
        LinearLayout card = col();
        card.setPadding(dp(16), dp(16), dp(16), dp(16));
        card.setBackground(background(SURFACE, LINE, 8));
        card.addView(text(client.name(chinese), 18, INK, true));
        space(card, 8);
        card.addView(text(source(client.source) + " · " + status(client.status), 14, GREEN, true));
        if (full || section == 2) {
            space(card, 12);
            card.addView(text(client.note(chinese), 16, INK, false));
            if (!client.unit.isEmpty()) {
                space(card, 10);
                card.addView(text(t("示例房源 ", "Minta lakás ") + client.unit, 14, MUTED, false));
            }
        }
        space(card, 12);
        card.addView(text(client.updated(), 13, MUTED, false));
        card.setContentDescription(client.name(chinese) + " " + t("详情", "adatlap"));
        card.setFocusable(true);
        card.setOnClickListener(v -> details(client));
        LinearLayout.LayoutParams lp = wrap();
        lp.bottomMargin = dp(12);
        parent.addView(card, lp);
    }
    private void details(DemoData.Client client) {
        LinearLayout content = col();
        content.setPadding(dp(24), dp(12), dp(24), dp(24));
        content.addView(text(t("演示记录 · 只读", "Mintaadat · Csak olvasható"), 14, GREEN, true));
        space(content, 20);
        detail(content, t("来源", "Forrás"), source(client.source));
        detail(content, t("状态", "Állapot"), status(client.status));
        detail(content, t("房源", "Lakás"), client.unit.isEmpty() ? t("暂未指定", "Nincs kiválasztva") : client.unit);
        detail(content, t("邮箱", "E-mail"), client.email());
        detail(content, t("最近跟进", "Utolsó kapcsolat"), client.updated());
        content.addView(text(client.note(chinese), 16, INK, false));
        ScrollView view = new ScrollView(this);
        view.addView(content);
        new AlertDialog.Builder(this).setTitle(client.name(chinese)).setView(view)
            .setPositiveButton(t("关闭", "Bezárás"), null).show();
    }
    private void detail(LinearLayout parent, String key, String value) {
        parent.addView(text(key, 13, MUTED, false));
        space(parent, 5);
        parent.addView(text(value, 16, INK, false));
        space(parent, 18);
    }

    private void followUps() {
        heading(t("销售跟进", "Kapcsolattartás"), t("按最近联系时间排序", "Legutóbbi kapcsolat szerint"));
        LinearLayout filters = row();
        filter(filters, t("全部", "Mind"), status == null, () -> { status = null; render(); });
        filter(filters, t("新询价", "Új"), status == DemoData.Status.NEW, () -> { status = DemoData.Status.NEW; render(); });
        filter(filters, t("已联系", "Elért"), status == DemoData.Status.CONTACTED, () -> { status = DemoData.Status.CONTACTED; render(); });
        body.addView(filters);
        space(body, 18);
        for (DemoData.Client client : DemoData.select("", null, status)) addClient(body, client, true);
    }

    private void properties() {
        heading(t("房源", "Lakások"), t("示例户型 · 非实际库存", "Mintalakások · Nem valós készlet"));
        for (int i = 1; i <= 4; i++) {
            String id = String.format(Locale.ROOT, "D-%02d", i);
            LinearLayout item = col();
            item.setPadding(0, dp(18), 0, dp(18));
            item.addView(text(t("示例房源 ", "Minta lakás ") + id, 19, INK, true));
            space(item, 10);
            item.addView(text(t("示例状态：", "Mintaállapot: ") + (i == 3 ? t("已预订", "Foglalt") : t("可售", "Elérhető")), 15, GREEN, false));
            space(item, 12);
            TextView action = command(t("关联示例客户", "Kapcsolódó mintaügyfelek"), () -> {
                query = id; source = null; navigate(1);
            });
            item.addView(action);
            body.addView(item);
            line(body);
        }
    }

    private void settings() {
        heading(t("设置", "Beállítások"), "Harmat CRM · 0.1.0");
        body.addView(text(t("界面语言", "Nyelv"), 17, INK, true));
        space(body, 14);
        LinearLayout languages = row();
        filter(languages, "中文", chinese, () -> setLanguage(true));
        filter(languages, "Magyar", !chinese, () -> setLanguage(false));
        body.addView(languages);
        subheading(t("连接状态", "Kapcsolat"));
        detail(body, t("数据源", "Adatforrás"), t("本机演示数据", "Helyi mintaadatok"));
        detail(body, t("正式 CRM", "Éles CRM"), t("未连接", "Nincs kapcsolat"));
        detail(body, t("写入权限", "Írási jogosultság"), t("关闭", "Letiltva"));
        detail(body, t("报价与预约", "Ajánlat és időpont"), t("未接入", "Nincs integrálva"));
    }
    private void setLanguage(boolean next) {
        chinese = next;
        getPreferences(MODE_PRIVATE).edit().putBoolean("chinese", next).apply();
        render();
    }
    @Override public void onBackPressed() {
        if (section != 0) navigate(0); else super.onBackPressed();
    }
}
