import app from 'flarum/admin/app';
import Tag from 'flarum/models/Tag';
import DiscussionComposer from 'flarum/components/DiscussionComposer';

export default class ChatBotDiscussionComposer extends DiscussionComposer {
    oninit(vnode: any) {
        console.log("ChatBotDiscussionComposer oninit before super oninit")
        super.oninit(vnode);
        console.log("ChatBotDiscussionComposer oninit after super oninit")
    }

    submit() {
        // 提交发帖子的逻辑
        console.log("ChatBotDiscussionComposer submit!!!")
        const title = this.title();
        const content = this.content();

        if (!title) {
            const chat_bot_tags = app.data.settings['ccdc-chatbot.enabled-tags'];
            const selected_tags = this.tags();
            const selected_tag_ids = selected_tags.map((tag: Tag) => tag.id());
            const selected_chat_bot_tags = chat_bot_tags.some((setting: string) => selected_tag_ids.includes(setting));
            if (selected_chat_bot_tags) {
                this.title(content.slice(0, 20));
            }
        }

        // 发送请求到后端
        app.store
            .createRecord('discussions')
            .save({ title, content })
            .then((discussion) => {
                this.composer.hide();
                app.discussions.refresh();
                m.route.set(app.route.discussion(discussion));
          }, this.loaded.bind(this));
    }
}