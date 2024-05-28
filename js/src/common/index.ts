import app from 'flarum/common/app';

app.initializers.add('ccdc/chatbot', () => {
  console.log('[ccdc/chatbot] Hello, forum and admin!');
});
