<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <h1 class="mb-6 text-2xl font-bold text-slate-800">Login</h1>

        <?php if (!empty($error)): ?>
            <div class="mb-4 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Username</label>
                <input type="text" name="username" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Password</label>
                <input type="password" name="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>

            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-white hover:bg-slate-800">
                Sign In
            </button>
        </form>
    </div>
</div>