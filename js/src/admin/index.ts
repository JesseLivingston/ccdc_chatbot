import app from 'flarum/admin/app';
import ChatBotSettings from './components/ChatBotSettings';
app.initializers.add('ccdc/chatbot', () => {
  console.log('[ccdc/chatbot] Hello, admin!');
  app.extensionData
    .for('ccdc-chatbot')
    .registerPermission(
      {
        label: app.translator.trans('ccdc-chatbot.admin.permissions.use_chatbot_assistant_label'),
        icon: 'fas fa-comment',
        permission: 'discussion.useChatBotAssistant',
        allowGuest: true,
      },
      'start'
    )
    .registerPage(ChatBotSettings);
});
