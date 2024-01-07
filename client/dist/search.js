document.addEventListener('DOMContentLoaded', () => {
  const targetElement = document.getElementById(
    'Menu-Sunnysideup-SiteWideSearch-Admin-SearchAdmin'
  )

  if (targetElement) {
    const securityID =
      window.ss && window.ss.config && window.ss.config['SecurityID']
    if (securityID) {
      const inputId = 'QuickSearchKeyword' + securityID
      const holder = document.createElement('a')
      // holder.setAttribute('href', '/admin/find/')
      const form = document.createElement('form')
      form.setAttribute('method', 'post') // or 'post', depending on your needs
      form.setAttribute('action', '/admin/find/EditForm') // Update with your form action

      const input = document.createElement('input')
      input.setAttribute('type', 'text')
      input.setAttribute('name', 'Keywords')
      input.setAttribute('placeholder', 'Search...')
      input.setAttribute('id', inputId)

      const hiddenInput = document.createElement('input')
      hiddenInput.setAttribute('type', 'hidden')
      hiddenInput.setAttribute('name', 'SecurityID')
      hiddenInput.setAttribute('value', securityID)
      // hiddenInput.setAttribute('id', 'Form_EditForm_SecurityID')

      const button = document.createElement('button')
      button.innerHTML = '&#128269;'
      button.setAttribute('class', 'left-search-button')
      button.onclick = () => (window.location.href = '/admin/find')

      while (targetElement.firstChild) {
        targetElement.removeChild(targetElement.firstChild)
      }

      form.appendChild(input)
      form.appendChild(hiddenInput)
      form.appendChild(button)
      form.classList.add('sunny-side-up-quick-search-form')
      holder.appendChild(form)
      targetElement.appendChild(holder)

      if (input) {
        // console.log('adding keyup event listener')
        input.addEventListener('keyup', function (event) {
          var keywords = this.value.trim()
          // console.log('keywords', keywords.length)
          if (keywords.length < 2) {
            button.style.display = 'block'
          } else {
            button.style.display = 'none'
          }
          if (event.key === 'Enter') {
            event.preventDefault() // Prevents the default form submit on Enter press
            if (keywords.length < 2) {
              window.location.href = '/admin/find/'
            } else {
              this.form.submit()
            }
          }
        })
      }
    }
  }
})
