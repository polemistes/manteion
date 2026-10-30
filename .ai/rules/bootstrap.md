---
paths:
  - bootstrap/app.php
---

# Bootstrap

## TrimStrings must leave a call's SDP alone (data.sdp.sdp)
Video-call signalling (POST messages.call → CallSignalController) carries WebRTC session descriptions in data.sdp.sdp. Every SDP line ends in CRLF, the last one too; the default TrimStrings middleware strips that trailing CRLF and the browser at the other end then throws "Failed to parse SessionDescription … Invalid SDP line" on setRemoteDescription, so the call never connects. Hence `$middleware->trimStrings(except: ['data.sdp.sdp'])` (except keys are full dotted paths). CallSignalTest covers it. Don't add other whitespace-sensitive payloads without extending the exception.
