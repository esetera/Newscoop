'''
Created on Jun 9, 2011

@package: Newscoop
@copyright: 2011 Sourcefabric o.p.s.
@license: http://www.gnu.org/licenses/gpl-3.0.txt
@author: Nistor Gabriel

Provides classes that provide general behaviour or functionality.
'''
from inspect import isclass
import inspect
from _abcoll import Iterable

# --------------------------------------------------------------------

# Flag indicating if the guarding should be enabled.
GUARD_ENABLED = __debug__

# --------------------------------------------------------------------

class Uninstantiable:
    '''
    Extending this class will not allow for the creation of any instance of the class.
    This has to be the first class inherited in order to properly work.
    '''
    
    def __new__(cls, *args, **keyargs):
        '''
        Does not allow you to create an instance.
        '''
        raise AssertionError('Cannot create an instance of "' + str(cls.__name__) + '" class');

# --------------------------------------------------------------------

class Singletone:
    '''
    Extending this class will always return the same instance.
    '''
    
    def __new__(cls):
        '''
        Will always return the same instance.
        '''
        if not hasattr(cls, '__instance'):
            cls.__instance = super().__new__(cls);
        return cls.__instance

# --------------------------------------------------------------------

class Protected:
    '''
    Extending this class will not allow for the creation of any instance of the class outside the module that has
    defined it.
    This has to be the first class inherited in order to properly work.
    '''
    
    def __new__(cls, *args, **keyargs):
        '''
        Does not allow you to create an instance outside the module of the class.
        '''
        if not _isSameModule(cls):
            raise AssertionError('Cannot create an instance of "' + str(cls.__name__) + \
                                 '" class from outside is module');
        return super().__new__(cls, *args, **keyargs);

# --------------------------------------------------------------------

def guard(*args, **keyargs):
    '''
    Provides if in debug mode a guardian for the decorated class. A guardian is checking if there are illegal calls to the guarder class.
    Illegal calls are classified as being calls to protected attributes or method (does that start with '_') and
    also whenever you try to change outside the class or super class a public attribute that already has a value.
    How to use:
    
    @guard
    class MyClass
    'In this case all rules apply'
    
    @guard(ifNoneSet='myName')
    class MyClass
    'In this case all rules apply except if the public attribute myName has a None value it is possible to assign a
    not None value only once'
    '''
    if len(args) == 1:
        if GUARD_ENABLED:
            return _guardClass(args[0])
        else:
            return args[0]
    elif len(keyargs) > 0:
        def guardClass(clazz):
            return _guardClass(clazz, keyargs)
        return guardClass
    else:
        raise AssertionError('Invalid arguments %s' % keyargs.keys())

class Guardian:
    '''
    Provides the guardian implementation. A guardian is checking if there are illegal calls to the guarder class.
    Illegal calls are classified as being calls to protected attributes or method (does that start with '_') and
    also whenever you try to change outside the class or super class a public attribute that already has a value.
    '''
        
    def __getattribute__(self, name):
        if name.startswith('_') and not name.startswith('__'):
            if not (_isSameModule(self) or _isSuperCall(self)):
                raise AssertionError('Illegal call, the (%s) is protected' % name)
        return super().__getattribute__(name)
        
    def __setattr__(self, name, value):
        check = False
        if name.startswith('_'):
            check = True
        else:
            try:
                oldValue = super().__getattribute__(name)
                check = True
            except AttributeError:
                pass
            if check:
                protect = self.__class__.protect_class
                if oldValue is None and 'ifNoneSet' in protect:
                    if name == protect['ifNoneSet'] or name in protect['ifNoneSet']:
                        check = False
        if check and not _isSuperCall(self):
            raise AssertionError(('Illegal call, attribute (%s) from %s can be' + 
                                 ' changed only from class or super class') % (name, self))
        super().__setattr__(name, value)
       
    def __delattr__(self, name):
        if not self.isSuperCall():
            raise AssertionError(('Illegal call, attribute (%s) from %s can be' + 
                                 ' deleted only from class or super class') % (name, self))
        super().__delattr__(name)

# --------------------------------------------------------------------

def withPrivate(mainClass):
    '''
    Used a class decorator, what this will allow is the use of a inner class in a main class
    as a container of private attributes and methods.
    As an example:
        
        @withPrivate
        class Ordered:
        
            class private(Criteria):
                
                def _register(self, name):
                    print(name)
            
        def order(self):
             self.private._register('hy')
             
    So in the class Order we have the _register method as being private (still can be called from other classes)
    but is just grouped in a specific class so it whon't show up at type hinting.  This decorator will combine
    the main class and private class into one and also set as an attribute as self.private = self so you can
    actually have type hinting and functionality.
    
    P.S. Only used this class when is really needed since it might create confusions, use whenever you can the '_'
        convention.
    
    @param mainClass: class
        The class that will be merged with the private inner class.
    '''
    privateClass = mainClass.private
    del mainClass.private # remove it because will be mixed with the main class
    
    def __init__(self, *args, **keyargs):
        self.private = self

    return type(mainClass.__name__, (mainClass, privateClass), {'__init__': __init__})

# --------------------------------------------------------------------

def fullyQName(obj):
    '''
    Provides the fully qualified class name of the instance or class provided.
    
    @param obj: class|object
        The class or instance to provide the fully qualified name for.
    '''
    if not isclass(obj):
        obj = obj.__class__
    return obj.__module__ + '.' + obj.__name__

def simpleName(obj):
    '''
    Provides the simple class name of the instance or class provided.
    
    @param obj: class|object
        The class or instance to provide the simple class name for.
    '''
    if not isclass(obj):
        obj = obj.__class__
    return obj.__name__

# --------------------------------------------------------------------

def _guardClass(clazz, protect=None):
    assert isclass(clazz), 'Invalid class provided %s' % clazz
    if GUARD_ENABLED:
        if not issubclass(clazz, Guardian):
            protect = protect or {}
            newClazz = type(clazz.__name__, (clazz, Guardian), {})
            newClazz.__module__ = clazz.__module__
            clazz = newClazz
        else:
            assert protect is not None, \
            'Class %s already guarded and there are no other options beside the inherited class' % clazz
            old = clazz.protect_class
            for name, value in old.items():
                if name in protect:
                    if isinstance(value, Iterable):
                        if isinstance(protect[name], Iterable):
                            protect[name] = value + protect[name]
                        else:
                            protect[name] = value + [protect[name]]
                    else:
                        if isinstance(protect[name], Iterable):
                            protect[name] = [value] + protect[name]
                        else:
                            protect[name] = [value, protect[name]]
                else:
                    protect[name] = value
        clazz.protect_class = protect
    return clazz

def _isSameModule(obj):
    if not isclass(obj):
        obj = obj.__class__
    calframe = inspect.getouterframes(inspect.currentframe(), 2)
    return inspect.getmodulename(calframe[2][1]) == obj.__module__

def _isSuperCall(obj):
    if not isclass(obj):
        obj = obj.__class__
    calframe = inspect.getouterframes(inspect.currentframe(), 2)
    locals = calframe[2][0].f_locals
    if 'self' in locals:
        return isinstance(locals['self'], obj)
    return False
    