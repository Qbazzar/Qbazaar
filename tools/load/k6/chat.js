// Chat: open WebSockets on Reverb plus a steady stream of messages
// (OPS-18.17, OPS-18.14).
//
//   k6 run -e BASE_URL=https://api.staging.example -e USERS_FILE=users.json \
//          -e REVERB_KEY=<staging REVERB_APP_KEY> chat.js
//
// Two scenarios run together:
//   sockets   ramps to SOCKETS open connections (default 2000), each
//             subscribed to its user channel and to its conversation, and
//             holds them; this is the "thousands of concurrent sockets" test.
//   messages  posts MSG_RATE messages a second (default 100) round-robin
//             over the users' conversations.
// Every message carries a send timestamp; sockets that receive it record
// chat_delivery_ms (POST -> queue -> Reverb -> socket), the number users feel.
//
// Users need `user_id` and `conversation_id` (README "Test users"). Each may
// send 30 messages a minute (qbazaar.messaging.rate_limit_per_minute), so
// MSG_RATE 100 needs at least 400 users with a conversation.

import http from 'k6/http';
import ws from 'k6/ws';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { BASE_URL, headers, nextUser, requireUsers } from './lib/config.js';

const pool = requireUsers('conversation_id');
const SOCKETS = Number(__ENV.SOCKETS || 2000);
const MSG_RATE = Number(__ENV.MSG_RATE || 100);
const RAMP_MINUTES = Number(__ENV.SOCKET_RAMP_MINUTES || 5);
const HOLD_MINUTES = Number(__ENV.HOLD_MINUTES || 10);
// A socket stays open for the whole ramp and hold, then the VU reconnects.
const SOCKET_LIFETIME_MS = (RAMP_MINUTES + HOLD_MINUTES) * 60000;
const WS_BASE = (__ENV.WS_URL || BASE_URL.replace(/^http/, 'ws')).replace(/\/+$/, '');

if (!__ENV.REVERB_KEY) {
  throw new Error('Set REVERB_KEY to the staging REVERB_APP_KEY (public; it is in the web bundle)');
}

const delivery = new Trend('chat_delivery_ms', true);
const received = new Counter('chat_messages_received');
const socketErrors = new Counter('chat_socket_errors');

export const options = {
  scenarios: {
    sockets: {
      executor: 'ramping-vus',
      exec: 'holdSocket',
      startVUs: 0,
      stages: [
        { target: SOCKETS, duration: `${RAMP_MINUTES}m` },
        { target: SOCKETS, duration: `${HOLD_MINUTES}m` },
        { target: 0, duration: '1m' },
      ],
      gracefulRampDown: '30s',
    },
    messages: {
      executor: 'constant-arrival-rate',
      exec: 'sendMessage',
      rate: MSG_RATE,
      timeUnit: '1s',
      startTime: `${RAMP_MINUTES}m`,
      duration: `${HOLD_MINUTES}m`,
      preAllocatedVUs: Math.max(20, MSG_RATE),
      maxVUs: MSG_RATE * 5,
    },
  },
  thresholds: {
    'http_req_duration{name:send}': ['p(95)<500'],
    'http_req_failed{name:send}': ['rate<0.01'],
    chat_delivery_ms: ['p(95)<1500'],
    chat_socket_errors: ['count<10'],
  },
};

// Private channels are authorised by the API with the user's Bearer token.
function authorise(user, socketId, channel) {
  const res = http.post(
    `${BASE_URL}/api/v1/broadcasting/auth`,
    { socket_id: socketId, channel_name: channel },
    { headers: headers(user), tags: { name: 'broadcast_auth' } },
  );
  return res.status === 200 ? res.json('auth') : null;
}

function subscribe(socket, user, socketId, channel) {
  const auth = authorise(user, socketId, channel);
  if (!auth) {
    socketErrors.add(1);
    return;
  }
  socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { auth, channel } }));
}

export function holdSocket() {
  const user = pool[(__VU - 1) % pool.length];
  const url = `${WS_BASE}/app/${__ENV.REVERB_KEY}?protocol=7&client=k6&version=8.4.0&flash=false`;

  const res = ws.connect(url, {}, (socket) => {
    socket.on('message', (raw) => {
      const frame = JSON.parse(raw);
      if (frame.event === 'pusher:connection_established') {
        const socketId = JSON.parse(frame.data).socket_id;
        subscribe(socket, user, socketId, `private-user.${user.user_id}`);
        subscribe(socket, user, socketId, `private-conversation.${user.conversation_id}`);
      } else if (frame.event === 'pusher:ping') {
        socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
      } else if (frame.event === 'message.sent') {
        const marker = /lt:(\d{13})/.exec(String(frame.data));
        if (marker) {
          delivery.add(Date.now() - Number(marker[1]));
          received.add(1);
        }
      } else if (frame.event === 'pusher:error') {
        socketErrors.add(1);
      }
    });
    socket.on('error', () => socketErrors.add(1));
    // Reverb drops a client silent for activity_timeout (30 s).
    socket.setInterval(() => socket.send(JSON.stringify({ event: 'pusher:ping', data: {} })), 25000);
    socket.setTimeout(() => socket.close(), SOCKET_LIFETIME_MS);
  });

  check(res, { 'socket upgraded (101)': (r) => r && r.status === 101 });
}

export function sendMessage() {
  const user = nextUser(pool);
  const res = http.post(
    `${BASE_URL}/api/v1/conversations/${user.conversation_id}/messages`,
    JSON.stringify({ body: `load test lt:${Date.now()}` }),
    { headers: { ...headers(user), 'Content-Type': 'application/json' }, tags: { name: 'send' } },
  );
  check(res, { 'message accepted': (r) => r.status === 201 || r.status === 200 });
}
