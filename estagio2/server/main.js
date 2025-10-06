import { Meteor } from 'meteor/meteor';
import moment from 'moment-timezone';

Meteor.startup(() => {
  const timezone = Meteor.settings.TZ || 'Europe/Madrid';
  moment.tz.setDefault(timezone);
});

  