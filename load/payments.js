// A ramp-up on the payments endpoint: from 1 to 40 virtual users in 30 s,
// each one paying 1.00 on invoice 1 then reading it again, like customers
// paying their invoice at the end of the month.
import http from 'k6/http';
import { check } from 'k6';

const BASE = __ENV.BASE_URL || 'http://app';

export const options = {
  scenarios: {
    ramp: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '30s', target: 40 },
        { duration: '20s', target: 40 },
        { duration: '5s', target: 0 },
      ],
    },
  },
};

export default function () {
  const res = http.post(`${BASE}/invoices/1/payments`, JSON.stringify({ amount: 1 }), {
    headers: { 'Content-Type': 'application/json' },
    tags: { name: 'POST /invoices/1/payments' },
  });
  check(res, { 'payment recorded': (r) => r.status === 201 });
  http.get(`${BASE}/invoices/1`, { tags: { name: 'GET /invoices/1' } });
}

// Once the load is over: does what is stored as paid match the payments?
export function teardown() {
  const balance = http.get(`${BASE}/invoices/1/payments`).json();
  console.log(`invoice 1: ${balance.payments} payments summing to ${balance.payments_sum}, stored as paid: ${balance.paid}`);
}
