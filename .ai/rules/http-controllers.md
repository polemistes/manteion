---
paths:
  - 'app/Http/Controllers/MessageImageController.php, app/Models/Message.php, app/Http/Controllers/ConversationMessageController.php'
---

# Http Controllers

## Chat pictures are private: local disk, served through the conversation gate
A message's picture (one per message, optional caption) is stored on the `local` disk under messages/{conversation_id}, never on the public disk: nothing under /storage may hold private-chat content, since those files have an open URL. The only way to a picture is messages.messages.image (MessageImageController), which authorises `view` on the conversation (participants and staff) and answers with `Cache-Control: private, max-age=1y, immutable`, so a browser fetches each picture once. Deleting a message must call Message::deleteImageFile() and null image_path/width/height in the same update; MessageSummary::preview() then shows "📷 Photo" (chat.preview.photo) for a picture without words, in the reader's locale.
