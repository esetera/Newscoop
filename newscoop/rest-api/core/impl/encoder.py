'''
Created on Jun 17, 2011

@package: Newscoop
@copyright: 2011 Sourcefabric o.p.s.
@license: http://www.gnu.org/licenses/gpl-3.0.txt
@author: Nistor Gabriel

Provides encoders implementations.
'''

from _abcoll import Callable
from xml.sax.saxutils import escape
from newscoop.core.spec.server import Encoder, EncoderPath, EncoderFactory
from newscoop.core.spec.resources import Path, Converter
from newscoop.core.spec import content_type

# --------------------------------------------------------------------

class EncoderBase(Encoder):
    '''
    Provides the base class for the encoders.
    Attention this class needs to be extended to provide the actual functionality.
    '''
    
    def __init__(self, out, encoderPath, factory):
        '''
        Initialize the encoder.
        
        @param out: Callable
            The output Callable used for writing content.
        @param encoderPath: EncoderPath
            The path encoder to be used by this content encoder when writing request paths.
        @param factory: EncoderBaseFactory
            The factory that created this encoder.
        '''
        assert isinstance(out, Callable), 'Invalid output Callable provided %s' % out
        assert isinstance(encoderPath, EncoderPath), 'Invalid encoder path %s' % encoderPath
        assert isinstance(factory, EncoderBaseFactory), 'Invalid factory %s' % factory
        self._out = out
        self._encoderPath = encoderPath
        self._factory = factory
        self.__nameStack = []
        self.__opened = True
    
    def isEmpty(self):
        '''
        Checks if the current encoder is empty, meaning that no block has been opened.
        
        @return: boolean
            True if the encoder is empty, false otherwise.
        '''
        return self.__opened and len(self.__nameStack) == 0
    
    def put(self, name, value, path=None):
        '''
        @see: Encoder.put
        '''
        assert isinstance(name, str), 'The name %s needs to be a string' % name
        assert path is None or isinstance(path, Path), 'Invalid path %s, can be None' % path
        assert self.__opened, 'The encoder is closed'
        assert not self.isEmpty(), 'You need to first open a root block'
    
    def open(self, name):
        '''
        @see: Encoder.open
        '''
        assert isinstance(name, str), 'The name %s needs to be a string' % name
        assert self.__opened, 'The encoder is closed'
        self.__nameStack.append(name)
    
    def close(self):
        '''
        @see: Encoder.close
        '''
        assert self.__opened, 'The encoder is closed'
        name = self.__nameStack.pop()
        if len(self.__nameStack) == 0:
            self.__opened = False
        return name

class EncoderBaseFactory(EncoderFactory):
    '''
    Provides the base encoders factory class.
    Attention this class needs to be extended to provide the actual functionality.
    '''
    
    def __init__(self, contentType, converter):
        '''
        @see: EncoderFactory.__init__
        
        @param converter: Converter
            The converter used by the encoders of this factory.
        '''
        super().__init__(contentType)
        assert isinstance(converter, Converter), 'Invalid converter %s' % converter
        self.converter = converter

    def isValidFormat(self, format):
        '''
        @see: EncoderFactory.isValidFormat
        '''
        assert isinstance(format, str), 'Invalid format %s' % format
        return self.contentType.format == format.lower()
    
# --------------------------------------------------------------------

class EncoderXMLIndented(EncoderBase):
    '''
    Provides encoding in XML form that also has proper indentation.
    '''

    def __init__(self, out, encoderPath, factory):
        '''
        @see: EncoderBase.__init__
        '''
        super().__init__(out, encoderPath, factory)
        self._indent = ''
    
    def put(self, name, value, path=None):
        '''
        @see: Encoder.put
        '''
        name = self._converter.normalize(name)
        super().put(name, value, path)
        self._out(self._indent)
        self._out('<')
        self._out(name)
        if path is not None:
            self._out(' href="')
            self._out(escape(path))
            self._out('"')
        if value is not None:
            self._out('>')
            self._out(escape(self._converter.asString(value)))
            self._out('</')
            self._out(name)
            self._out('>')
        else:
            self._out('/>')
        self._out(self._factory.lineEnd)
        
    def open(self, name):
        '''
        @see: Encoder.open
        '''
        if self.isEmpty():
            self._out('<?xml version="1.0" encoding="utf-8"?>')
            self._out(self._factory.lineEnd)
        name = self._converter.normalize(name)
        super().open(name)
        self._out(self._indent)
        self._out('<')
        self._out(name)
        self._out('>')
        self._out(self._factory.lineEnd)
        self._indent += self._factory.indented
        
    def close(self):
        '''
        @see: Encoder.close
        '''
        name = super().close()
        self._indent = self._indent[:-len(self._factory.indented)]
        self._out(self._indent)
        self._out('</')
        self._out(name)
        self._out('>')
        self._out(self._factory.lineEnd)
        return name

class EncoderXMLFactory(EncoderBaseFactory):
    '''
    Provides the XML encoders factory.
    '''
    
    def __init__(self, converter, indented='    ', lineEnd='\n'):
        '''
        @see: EncoderBaseFactory.__init__
        
        @param indented: string
            The indented block to use default 4 spaces, can be changed.
        @param lineEnd: string
            The line end to use by default \n, can be changed.
        '''
        super().__init__(content_type.XML, converter)
        self.indented = indented
        self.lineEnd = lineEnd

    def createEncoder(self, encoderPath, out):
        '''
        @see: EncoderFactory.createEncoder
        '''
        return EncoderXMLIndented(out, encoderPath, self)
        
# --------------------------------------------------------------------