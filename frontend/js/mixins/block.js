export default {
  props: {
    name: {
      type: String,
      required: true
    },
    isOpen: {
      type: Boolean,
      default: false
    },
    parentEditorName: {
      type: String,
      required: false
    },
    parentEditorId: {
      type: Number,
      required: false
    }
  },
  data: function () {
    return {
      opened: this.isOpen
    }
  },
  methods: {
    open: function () {
      this.opened = true
    },
    fieldName: function (id) {
      return this.name + '[' + id + ']' // output : nameOfBlock[UniqID][name]
    },
    getParentBlockData: function () {
      return {
        name: this.parentEditorName,
        id: this.parentEditorId
      }
    },
    repeaterName: function (id) {
      return this.name.replace('[', '-').replace(']', '') + '_' + id // nameOfBlock-UniqID_name
    }
  }
}
