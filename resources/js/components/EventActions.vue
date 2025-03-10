<template>
  <div>
    <b-dropdown variant="primary" :text="$translate('events.event_actions').toUpperCase()" class="deepnowrap">
      <div v-if="canedit">
        <b-dropdown-item :href="'/party/edit/' + idevents">
          {{ $translate('events.edit_event') }}
        </b-dropdown-item>
        <b-dropdown-item :href="'/party/duplicate/' + idevents">
          {{ $translate('events.duplicate_event') }}
        </b-dropdown-item>
        <b-dropdown-item @click="confirmDelete" v-if="candelete">
          {{ $translate('events.delete_event') }}
        </b-dropdown-item>
        <b-dropdown-item @click="confirmDelete" v-else-if="isAdmin" disabled>
          {{ $translate('events.delete_event') }}
        </b-dropdown-item>
        <div v-if="finished">
          <b-dropdown-item data-toggle="modal" data-target="#event-request-review">
            {{ $translate('events.request_review') }}
          </b-dropdown-item>
          <b-dropdown-item data-toggle="modal" data-target="#event-share-stats">
            {{ $translate('events.share_event_stats') }}
          </b-dropdown-item>
          <b-dropdown-item :href="'/export/devices/event/' + idevents">
            {{ $translate('devices.export_event_data') }}
          </b-dropdown-item>
        </div>
        <div v-else>
          <b-dropdown-item data-toggle="modal" data-target="#event-invite-to" v-if="isAttending && upcoming && approved">
            {{ $translate('events.invite_volunteers') }}
          </b-dropdown-item>
          <b-dropdown-item v-b-tooltip.hover id="invite-when-approved" data-toggle="modal" v-else-if="isAttending && upcoming" :title="$translate('events.invite_when_approved')" disabled>
            {{ $translate('events.invite_volunteers') }}
          </b-dropdown-item>
          <b-dropdown-item :href="'/party/join/' + idevents" v-else>
            {{ $translate('events.RSVP') }}
          </b-dropdown-item>
          <b-dropdown-item :href="'/group/join/' + event.group.idgroups" v-if="!inGroup">
            {{ $translate('events.follow_group') }}
          </b-dropdown-item>
        </div>
      </div>
      <div v-else>
        <b-dropdown-item data-toggle="modal" data-target="#event-share-stats" v-if="finished">
          {{ $translate('events.share_event_stats') }}
        </b-dropdown-item>
        <div v-else>
          <b-dropdown-item :href="'/group/join/' + event.group.idgroups" v-if="!inGroup">
            {{ $translate('events.follow_group') }}
          </b-dropdown-item>
          <b-dropdown-item data-toggle="modal" data-target="#event-invite-to" v-if="attending && upcoming">
            {{ $translate('events.invite_volunteers') }}
          </b-dropdown-item>
          <b-dropdown-item :href="'/party/join/' + idevents" v-else>
            {{ $translate('events.RSVP') }}
          </b-dropdown-item>
        </div>
      </div>
    </b-dropdown>
    <ConfirmModal @confirm="confirmedDelete" :message="$translate('events.confirm_delete')" ref="confirmdelete" />
  </div>
</template>
<script>
import event from '../mixins/event'
import ConfirmModal from './ConfirmModal'

export default {
  components: {ConfirmModal},
  mixins: [ event ],
  props: {
    idevents: {
      type: Number,
      required: true
    },
    canedit: {
      type: Boolean,
      required: false,
      default: false
    },
    candelete: {
      type: Boolean,
      required: false,
      default: false
    },
    isAttending: {
      type: Boolean,
      required: false,
      default: false
    },
    isAdmin: {
      type: Boolean,
      required: true
    },
    inGroup: {
      type: Boolean,
      required: false,
      default: false
    },
  },
  methods: {
    confirmDelete() {
      this.$refs.confirmdelete.show()
    },
    async confirmedDelete() {
      await this.$store.dispatch('events/delete', {
        idevents: this.idevents
      })

      // TODO LATER Assumes always works.
      window.location = '/party'
    }
  }
}
</script>