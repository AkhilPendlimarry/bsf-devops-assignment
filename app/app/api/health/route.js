export const dynamic = 'force-dynamic';
export function GET() {
  return Response.json({ ok: true, instance: process.env.INSTANCE_NAME || 'unknown' });
}
