# Phase 3 — Messaging

## 5.13 Design

Messaging is modeled as a domain, not as a generic CRUD table.

### Domain

```
Conversation
├── Participants
│   ├── Client
│   └── Professional
└── Messages
    ├── sender
    └── body
```

A client-professional conversation is unique per pair. A transaction/order is optional context and does not create a second conversation.

Support is reserved by the conversation type and participant model, but support creation is intentionally not exposed in V1. This prevents Phase 3 from fabricating a support workflow before Phase 4 administration is defined.

### Authorization

Access is based on the `conversation_participants` table.

The application never trusts:

- a client-supplied participant list;
- a client-supplied sender ID;
- a conversation ID without checking participation.

The sender is always the authenticated user.

This protects against IDOR/BOLA.

### Messages

Messages are append-only in V1:

- no arbitrary update endpoint;
- no arbitrary delete endpoint;
- body is validated server-side;
- maximum body size is 5,000 characters;
- duplicate identical messages from the same sender within 10 seconds are rejected.

The conversation stores `last_message_id` for efficient list rendering.

### Unread messages

Each participant stores `last_read_message_id`.

Unread count is therefore:

```
messages
WHERE conversation_id = ?
AND sender_id != current_user
AND id > last_read_message_id
```

This avoids creating one read row for every message and scales much better than a per-message read table for V1.

Mark-as-read advances the marker to the latest message while holding the participant row lock.

### Pagination

Conversation lists and message history use cursor pagination.

Messages are ordered by descending primary key and indexed by:

```
(conversation_id, id)
```

This is appropriate for chat/infinite-scroll workloads and avoids large OFFSET scans.

### Anti-spam

Two layers are used:

1. API rate limiting:
   - 30 messages/minute per authenticated user;
   - 10 messages/minute per conversation.
2. Duplicate-content suppression:
   - identical body from the same sender in the same conversation within 10 seconds is rejected.

More advanced abuse controls belong to the moderation/admin phase.

### Concurrency

Conversation creation is transactional and the professional row is locked before checking/creating the pair.

Sending a message locks the conversation, creates the message, then updates `last_message_id` in the same transaction.

Read state is also updated transactionally.

### Notifications

Message notifications are deliberately not coupled into 5.13. They belong to 5.14 transactional notifications, where delivery preferences, channels, queues and retry behavior will be defined centrally.

### Future evolution

The current model leaves room for:

- support conversations;
- attachments;
- message reports;
- moderation;
- blocking;
- delivery/read events;
- WebSockets/realtime broadcasting;
- queued notifications.

None of these are fabricated in the V1 domain until their business rules are defined.
