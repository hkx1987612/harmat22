package hu.harmat22.crm.preview;

import java.text.Normalizer;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.Comparator;
import java.util.List;
import java.util.Locale;

/** Entirely synthetic fixtures. Never replace these with a customer export. */
public final class DemoData {
    public static final LocalDate DATE = LocalDate.of(2026, 11, 5);
    public enum Source { WEBSITE, WALK_IN }
    public enum Status { NEW, CONTACTED, VISIT }

    public static final class Client {
        public final String id, zh, hu, unit, noteZh, noteHu;
        public final Source source;
        public final Status status;
        public final int ageDays;
        public final String time;

        Client(String id, String zh, String hu, String unit, Source source,
               Status status, int ageDays, String time, String noteZh, String noteHu) {
            this.id = id;
            this.zh = zh;
            this.hu = hu;
            this.unit = unit;
            this.source = source;
            this.status = status;
            this.ageDays = ageDays;
            this.time = time;
            this.noteZh = noteZh;
            this.noteHu = noteHu;
        }
        public String name(boolean chinese) { return chinese ? zh : hu; }
        public String note(boolean chinese) { return chinese ? noteZh : noteHu; }
        public String updated() { return DATE.minusDays(ageDays) + " " + time; }
        public String email() { return id.toLowerCase(Locale.ROOT) + "@example.invalid"; }
    }

    public static final class Day {
        public final LocalDate date;
        public final int visitors, views, website, walkIn;
        Day(int ago, int visitors, int views, int website, int walkIn) {
            date = DATE.minusDays(ago);
            this.visitors = visitors;
            this.views = views;
            this.website = website;
            this.walkIn = walkIn;
        }
        public int enquiries() { return website + walkIn; }
    }

    public static final List<Client> CLIENTS = Collections.unmodifiableList(Arrays.asList(
        new Client("DEMO-01", "示例客户 01", "Minta ügyfél 01", "D-01", Source.WEBSITE,
            Status.NEW, 0, "14:30", "想了解两居室及阳台面积。", "Kétszobás lakás és az erkély területe iránt érdeklődik."),
        new Client("DEMO-02", "示例客户 02", "Minta ügyfél 02", "D-03", Source.WALK_IN,
            Status.VISIT, 0, "13:45", "到访后希望了解不同楼层的户型。", "A személyes látogatás után több emelet alaprajzát szeretné megismerni."),
        new Client("DEMO-03", "示例客户 03", "Minta ügyfél 03", "", Source.WEBSITE,
            Status.CONTACTED, 1, "16:10", "关注绿化和周边交通，等待补充资料。", "A zöldterület és a közlekedés érdekli; további információra vár."),
        new Client("DEMO-04", "示例客户 04", "Minta ügyfél 04", "D-02", Source.WALK_IN,
            Status.CONTACTED, 2, "10:20", "想比较两个户型，请再次联系。", "Két alaprajzot szeretne összehasonlítani; visszahívást kér."),
        new Client("DEMO-05", "示例客户 05", "Minta ügyfél 05", "D-04", Source.WEBSITE,
            Status.NEW, 3, "15:05", "咨询储藏室和车位。", "Tárolóról és parkolóhelyről érdeklődik."),
        new Client("DEMO-06", "示例客户 06", "Minta ügyfél 06", "D-01", Source.WALK_IN,
            Status.VISIT, 4, "11:00", "已了解项目，后续与家人一起到访。", "Megismerte a projektet; családjával újra ellátogatna.")
    ));
    public static final List<Day> DAYS = Collections.unmodifiableList(Arrays.asList(
        new Day(0, 82, 318, 3, 2), new Day(1, 76, 294, 2, 3),
        new Day(2, 91, 356, 4, 1), new Day(3, 58, 207, 1, 0),
        new Day(4, 63, 241, 2, 1), new Day(5, 85, 339, 3, 2), new Day(6, 74, 283, 2, 1)
    ));

    public static List<Client> select(String query, Source source, Status status) {
        String needle = normalize(query);
        List<Client> result = new ArrayList<>();
        for (Client client : CLIENTS) {
            String haystack = normalize(client.id + " " + client.zh + " " + client.hu + " " + client.unit);
            if ((source == null || source == client.source) && (status == null || status == client.status)
                    && haystack.contains(needle)) result.add(client);
        }
        result.sort(Comparator.comparing(Client::updated).reversed().thenComparing(c -> c.id));
        return result;
    }

    public static int[] totals() {
        int[] totals = new int[4];
        for (Day day : DAYS) {
            totals[0] += day.views;
            totals[1] += day.website;
            totals[2] += day.walkIn;
            totals[3] += day.enquiries();
        }
        return totals;
    }

    private static String normalize(String value) {
        return Normalizer.normalize(value == null ? "" : value, Normalizer.Form.NFD)
            .replaceAll("\\p{M}+", "").trim().toLowerCase(Locale.ROOT);
    }
    private DemoData() {}
}
