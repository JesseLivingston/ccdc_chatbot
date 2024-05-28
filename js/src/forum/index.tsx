import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PostUser from 'flarum/forum/components/PostUser';

app.initializers.add('ccdc/chatbot', () => {
  // console.log('[ccdc/chatbot] Hello, forum!!');
  extend(PostUser.prototype, 'view', function (view) {
    const user = this.attrs.post.user();
    // console.log('chatBotUserPromptId: ' + app.forum.attribute('chatBotUserPromptId'));
    // console.log('current post user id: ' + user.id());
    if (!user || app.forum.attribute('chatBotUserPromptId') !== user.id()) return;
    // console.log('-----------------' + app.forum.attribute('chatBotBadgeText'));
    view.children.push(
      <div className="UserPromo-badge">
        <div className="badge">{app.forum.attribute('chatBotBadgeText')}</div>
      </div>
    );
  });
});
