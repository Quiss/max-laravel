<?php

namespace MaxBot\Webhook;

enum UpdateType: string
{
    case MessageCreated = 'message_created';
    case MessageCallback = 'message_callback';
    case DialogCleared = 'dialog_cleared';
    case DialogMuted = 'dialog_muted';
    case DialogUnmuted = 'dialog_unmuted';
    case DialogRemoved = 'dialog_removed';
    case MessageEdited = 'message_edited';
    case MessageRemoved = 'message_removed';
    case BotAdded = 'bot_added';
    case BotRemoved = 'bot_removed';
    case BotStarted = 'bot_started';
    case BotStopped = 'bot_stopped';
    case UserAdded = 'user_added';
    case UserRemoved = 'user_removed';
    case ChatTitleChanged = 'chat_title_changed';
}
