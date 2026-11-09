package hu.harmat22.crm.mobile;

/** Main-thread ownership for asynchronous operations, including reused owner objects. */
final class CallbackOwner<T> {
    private long epoch;
    private T owner;

    long begin(T value) {
        if (value == null) throw new IllegalArgumentException("Missing callback owner");
        owner = value;
        return ++epoch;
    }

    boolean matches(long token, T value) {
        return owner != null && owner == value && epoch == token;
    }

    boolean finish(long token, T value) {
        if (!matches(token, value)) return false;
        invalidate();
        return true;
    }

    void invalidate() { owner = null; epoch++; }
}
