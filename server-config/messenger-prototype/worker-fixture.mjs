// LOCAL MINIFLARE TEST ENTRY ONLY. Never use this as the deployed Worker entry.
import worker, { HarmatGuard } from './worker.mjs';

export class TestGuard extends HarmatGuard {
  async fetch(request) {
    const path = new URL(request.url).pathname;
    if (path === '/__test/seed' && request.method === 'POST') {
      await this.ctx.storage.put('safety-v1', await request.json());
      return new Response('seeded');
    }
    if (path === '/__test/state') {
      return new Response(JSON.stringify(await this.ctx.storage.get('safety-v1') ?? null),
        { headers: { 'content-type': 'application/json' } });
    }
    return super.fetch(request);
  }
}

export default worker;
