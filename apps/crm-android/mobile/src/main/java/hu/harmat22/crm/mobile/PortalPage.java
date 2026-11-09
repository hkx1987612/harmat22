package hu.harmat22.crm.mobile;

import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.nodes.TextNode;
import org.jsoup.select.Elements;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Locale;

/** Parses only already-authorized server-rendered data; never follows row links. */
public final class PortalPage {
    public static final class AuthRequired extends Exception {}
    public static final class SchemaChanged extends Exception {}
    public static final class Field {
        public final String label, value;
        Field(String label, String value) { this.label = label; this.value = value; }
    }
    public static final class Record {
        public final String title, subtitle, status;
        public final List<Field> fields;
        Record(String title, String subtitle, String status, List<Field> fields) {
            this.title = title; this.subtitle = subtitle; this.status = status;
            this.fields = java.util.Collections.unmodifiableList(new ArrayList<>(fields));
        }
        public boolean matches(String query) {
            String needle = query.trim().toLowerCase(Locale.ROOT);
            if (needle.isEmpty()) return true;
            if ((title + " " + subtitle + " " + status).toLowerCase(Locale.ROOT).contains(needle)) return true;
            for (Field field : fields) if (field.value.toLowerCase(Locale.ROOT).contains(needle)) return true;
            return false;
        }
    }
    public final String view, user, role;
    public final List<Field> metrics = new ArrayList<>();
    public final List<List<String>> days = new ArrayList<>();
    public final List<Record> records = new ArrayList<>();
    public final long fetchedAt = System.currentTimeMillis();

    private PortalPage(String view, String user, String role) { this.view = view; this.user = user; this.role = role; }

    public static PortalPage parse(String html, String view) throws AuthRequired, SchemaChanged {
        if (!PortalPolicy.VIEWS.contains(view)) throw new SchemaChanged();
        Document document = Jsoup.parse(html);
        Element body = document.selectFirst("body.harmat-sales-portal-body");
        if (body == null || body.selectFirst(".harmat-sales-user") == null) throw new AuthRequired();
        PortalPage page = new PortalPage(view, text(body.selectFirst(".harmat-sales-user > span")),
            text(body.selectFirst(".harmat-sales-role-badge")));
        if (view.equals("dashboard")) {
            for (Element metric : body.select(".harmat-sales-traffic-strip > span")) {
                page.metrics.add(new Field(text(metric.selectFirst("small")), text(metric.selectFirst("strong"))));
            }
            for (Element row : body.select(".harmat-sales-traffic-day-table tbody > tr")) {
                Elements cells = directCells(row);
                if (cells.size() != 6) throw new SchemaChanged();
                List<String> day = new ArrayList<>();
                day.add(text(cells.get(0).selectFirst("small")));
                for (int i = 1; i < cells.size(); i++) day.add(cellText(cells.get(i)));
                page.days.add(java.util.Collections.unmodifiableList(day));
            }
            page.days.sort(Comparator.comparing((List<String> day) -> day.get(0)).reversed());
            for (Element item : body.select(".harmat-sales-dashboard-item")) {
                page.records.add(new Record(text(item.selectFirst("header strong")), text(item.selectFirst("p")),
                    text(item.selectFirst("header span")), java.util.Collections.singletonList(new Field("", text(item.selectFirst("small"))))));
            }
            if (body.selectFirst(".harmat-sales-overview-lists") == null) throw new SchemaChanged();
            return page;
        }
        String tableClass = switch (view) {
            case "inquiries" -> ".harmat-sales-inquiry-table";
            case "clients" -> ".harmat-sales-client-table";
            case "deals" -> ".harmat-sales-deal-table";
            default -> ".harmat-sales-property-table";
        };
        Element table = body.selectFirst(tableClass);
        if (table == null) {
            // Staff may have no access to a view. Never turn unrelated tables into CRM data.
            if (body.selectFirst(".harmat-sales-empty") != null) return page;
            throw new SchemaChanged();
        }
        Elements headers = table.select("thead > tr > th");
        int expected = view.equals("inquiries") ? 9 : view.equals("clients") ? 7 : view.equals("properties") ? 12 : 0;
        if ((expected > 0 && headers.size() != expected) || (view.equals("deals") && headers.size() != 11 && headers.size() != 12)) throw new SchemaChanged();
        for (Element row : table.select("tbody > tr")) {
            Elements cells = directCells(row);
            if (cells.size() == 1 && cells.get(0).hasAttr("colspan")) continue;
            if (cells.size() != headers.size()) throw new SchemaChanged();
            int titleColumn = view.equals("inquiries") ? 2 : 0;
            int propertyColumn = view.equals("inquiries") ? 3 : view.equals("properties") ? 5 : 1;
            int statusColumn = view.equals("inquiries") ? 5 : view.equals("properties") ? 1 : view.equals("clients") ? 1 : 2;
            String title = text(cells.get(titleColumn).selectFirst("strong"));
            if (title.isEmpty()) title = cellText(cells.get(titleColumn));
            List<Field> fields = new ArrayList<>();
            for (int i = 0; i < cells.size(); i++) {
                if (i == cells.size() - 1 || (view.equals("properties") && i == 10)) continue;
                String value = cellText(cells.get(i));
                if (!value.isEmpty()) fields.add(new Field(headers.get(i).text(), value));
            }
            Element pill = cells.get(statusColumn).selectFirst(".harmat-sales-contact-progress, .harmat-sales-pill, select");
            String status = pill == null ? cellText(cells.get(statusColumn)) : cellText(pill);
            page.records.add(new Record(title, cellText(cells.get(propertyColumn)), status, fields));
        }
        // The website sorts enquiries with JavaScript. Apply its ISO-date order natively.
        if (view.equals("inquiries")) page.records.sort(Comparator.comparing((Record record) -> record.fields.get(1).value).reversed());
        return page;
    }

    private static String text(Element element) { return element == null ? "" : element.text().trim(); }

    private static Elements directCells(Element row) {
        Elements cells = new Elements();
        for (Element child : row.children()) if (child.normalName().equals("td")) cells.add(child);
        return cells;
    }

    private static String cellText(Element source) {
        if (source.normalName().equals("select")) {
            Element selected = source.selectFirst("option[selected]");
            return text(selected == null ? source.selectFirst("option") : selected);
        }
        Element copy = source.clone();
        copy.select("script, style, button, input[type=hidden], summary, .harmat-sales-row-more").remove();
        for (Element select : copy.select("select")) {
            Element selected = select.selectFirst("option[selected]");
            if (selected == null) selected = select.selectFirst("option");
            select.replaceWith(new TextNode(text(selected) + " "));
        }
        for (Element input : copy.select("input, textarea")) {
            String name = input.attr("name");
            input.replaceWith(new TextNode((name.equals("property_price") || name.equals("sales_note"))
                ? (input.tagName().equals("textarea") ? input.text() : input.attr("value")) : ""));
        }
        for (Element part : copy.select("small, strong, span, p, br")) part.appendText(" ");
        return copy.text().trim();
    }
}
